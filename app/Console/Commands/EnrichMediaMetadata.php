<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Services\MediaUploader;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EnrichMediaMetadata extends Command
{
    protected $signature = 'app:enrich-media-metadata {--write : Guarda hashes y dimensiones; sin esta opción solo diagnostica} {--chunk=100 : Registros por bloque (1 a 1000)} {--limit=1000 : Máximo de registros por ejecución (1 a 10000)} {--after=0 : Continuar después de este ID}';

    protected $description = 'Diagnostica y enriquece metadatos sin mover, regenerar ni borrar archivos';

    public function handle(MediaUploader $uploader): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($chunk === false) {
            $this->error('El tamaño de bloque debe estar entre 1 y 1000.');

            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        $after = filter_var($this->option('after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($limit === false || $after === false) {
            $this->error('El límite debe estar entre 1 y 10000 y el ID de continuación no puede ser negativo.');

            return self::FAILURE;
        }
        $hasHash = Schema::hasColumn('media_assets', 'content_hash');
        if ($this->option('write') && ! $hasHash) {
            $this->error('Aplica primero la migración de content_hash.');

            return self::FAILURE;
        }
        $this->info($this->option('write') ? 'Enriquecimiento con escritura.' : 'Diagnóstico sin escritura.');
        $seen = [];
        $counts = ['inspected' => 0, 'missing' => 0, 'duplicates' => 0, 'updated' => 0];
        $lastId = $after;
        MediaAsset::query()->where('id', '>', $after)->limit($limit)->with('media')->chunkById($chunk, function ($assets) use ($uploader, $hasHash, &$seen, &$counts, &$lastId): void {
            foreach ($assets as $asset) {
                $lastId = $asset->id;
                $counts['inspected']++;
                $media = $asset->getFirstMedia('images');
                if (! $media || config('filesystems.disks.'.$media->disk.'.driver') !== 'local'
                    || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
                    $counts['missing']++;
                    $this->warn("Medio {$asset->id}: original ausente o disco no local; no se modifica.");

                    continue;
                }
                $hash = hash_file('sha256', Storage::disk($media->disk)->path($media->getPathRelativeToRoot()));
                if ($hash === false) {
                    $counts['missing']++;

                    continue;
                }
                $canonical = $hasHash ? MediaAsset::query()->where('content_hash', $hash)->value('id') : null;
                $canonical ??= $seen[$hash] ?? $asset->id;
                $seen[$hash] = $canonical;
                $duplicate = (int) $canonical !== $asset->id;
                if ($duplicate) {
                    $counts['duplicates']++;
                    $this->warn("Medio {$asset->id}: contenido duplicado del medio {$canonical}; se conservan ambos.");
                }
                if (! $media->getCustomProperty('original_filename')) {
                    $this->line("Medio {$asset->id}: nombre original completo desconocido; no se inventa.");
                }
                if (! $this->option('write')) {
                    continue;
                }
                try {
                    DB::transaction(function () use ($asset, $media, $hash, $duplicate, $uploader): void {
                        $current = MediaAsset::query()->lockForUpdate()->find($asset->id);
                        $currentMedia = $current?->media()->whereKey($media->id)->first();
                        if (! $currentMedia) {
                            return;
                        }
                        if (! $duplicate && $current->content_hash === null) {
                            $current->content_hash = $hash;
                            $current->save();
                        }
                        $dimensions = $uploader->dimensions($currentMedia);
                        if ($currentMedia->getCustomProperty('dimensions') !== $dimensions) {
                            $currentMedia->setCustomProperty('dimensions', $dimensions);
                            $currentMedia->save();
                        }
                    });
                    $counts['updated']++;
                } catch (UniqueConstraintViolationException) {
                    $this->warn("Medio {$asset->id}: hash asignado por otra operación; no se sobrescribe.");
                }
            }
        });
        $this->info("Inspeccionados: {$counts['inspected']}; ausentes: {$counts['missing']}; duplicados: {$counts['duplicates']}; procesados con escritura: {$counts['updated']}.");
        $this->line("Para continuar: --after={$lastId}. Los duplicados sin hash se comparan dentro de este tramo.");

        return self::SUCCESS;
    }
}
