<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MySqlMediaConcurrencyTest extends TestCase
{
    public function test_simultaneous_uploads_converge_on_one_original_in_independent_mysql_database(): void
    {
        $database = getenv('MYSQL_MEDIA_TEST_DATABASE');
        if (! $database) {
            $this->markTestSkipped('MYSQL_MEDIA_TEST_DATABASE no configurada; requiere una base MySQL de pruebas independiente.');
        }
        $connection = [...config('database.connections.mysql'), 'database' => $database, 'url' => null];
        $this->assertNotSame(config('database.connections.mysql.database'), $database, 'No usar la base del proyecto.');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+_test$/', $database, 'La base debe estar identificada con sufijo _test.');
        config(['database.connections.media_test' => $connection]);
        DB::setDefaultConnection('media_test');
        foreach (['media_assets', 'media', 'users', 'posts', 'services', 'testimonials', 'search_settings'] as $table) {
            if (Schema::hasTable($table)) {
                $this->assertSame(0, DB::table($table)->count(), 'La base de pruebas debe estar vacía de datos.');
            }
        }
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'media_test', '--force' => true, '--no-interaction' => true]));
        $user = User::factory()->create();
        $userId = $user->id;
        $root = sys_get_temp_dir().'/media-concurrency-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($root);
        $source = UploadedFile::fake()->image('source.png', 600, 400);
        File::copy($source->getRealPath(), $root.'/source.png');
        config(['filesystems.disks.public.root' => $root.'/public']);
        Storage::forgetDisk('public');
        try {
            $tasks = [];
            foreach ([0, 1] as $index) {
                $tasks[] = static function () use ($connection, $userId, $root, $index): array {
                    config(['database.connections.media_test' => $connection, 'cache.default' => 'array', 'filesystems.disks.public.root' => $root.'/public']);
                    DB::setDefaultConnection('media_test');
                    Storage::forgetDisk('public');
                    MediaAsset::creating(static function () use ($root, $index): void {
                        touch($root.'/ready-'.$index);
                        $deadline = microtime(true) + 10;
                        while (! is_file($root.'/ready-0') || ! is_file($root.'/ready-1')) {
                            if (microtime(true) > $deadline) {
                                throw new \RuntimeException('La segunda carga no llegó a la barrera de concurrencia.');
                            }
                            usleep(10000);
                        }
                    });
                    $result = app(MediaUploader::class)->upload(User::findOrFail($userId), new UploadedFile($root.'/source.png', 'equipo.png', null, null, true), 'Equipo', 'Personas');

                    return ['created' => $result['created'], 'id' => $result['asset']->id];
                };
            }
            $results = Concurrency::driver('process')->run($tasks, timeout: 20);
            $this->assertSame($results[0]['id'], $results[1]['id']);
            $this->assertSame(1, count(array_filter(array_column($results, 'created'))));
            $this->assertSame(1, MediaAsset::count());
            $this->assertSame(1, DB::table('media')->count());
            $this->assertCount(3, Storage::disk('public')->allFiles());
        } finally {
            MediaAsset::query()->each(fn (MediaAsset $asset) => $asset->delete());
            $user->delete();
            File::deleteDirectory($root);
        }
    }
}
