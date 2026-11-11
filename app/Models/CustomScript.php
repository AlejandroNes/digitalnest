<?php

namespace App\Models;

use App\Services\ApplicationCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

#[Fillable(['name', 'code', 'position', 'purpose', 'is_active'])]
class CustomScript extends Model
{
    use HasFactory;

    public const POSITIONS = ['head' => 'Head', 'body_start' => 'Inicio del Body', 'body_end' => 'Final del Body'];

    public const PURPOSES = ['necessary' => 'Verificación sin seguimiento', 'statistics' => 'Analítica', 'marketing' => 'Publicidad', 'preferences' => 'Widget o funcionalidad opcional'];

    protected $attributes = ['is_active' => false];

    protected function casts(): array
    {
        return ['code' => 'encrypted', 'is_active' => 'boolean'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::saving(function (self $script): void {
            $script->code_hash = hash('sha256', trim($script->code));
            if (auth()->id()) {
                $script->updated_by = auth()->id();
                if (! $script->exists) {
                    $script->created_by = auth()->id();
                }
            }
        });
        static::saved(function (self $script): void {
            ApplicationCache::forget(ApplicationCache::SCRIPTS);
            if (($script->is_active || $script->getOriginal('is_active'))
                && ($script->purpose !== 'necessary' || $script->getOriginal('purpose') !== 'necessary')
                && ($script->wasRecentlyCreated || $script->wasChanged(['code', 'purpose', 'position', 'is_active']))) {
                SearchSetting::renewConsent();
            }
            Log::info('custom_script.saved', ['script_id' => $script->id, 'actor_id' => $script->updated_by]);
        });
        static::deleted(function (self $script): void {
            ApplicationCache::forget(ApplicationCache::SCRIPTS);
            if ($script->is_active && $script->purpose !== 'necessary') {
                SearchSetting::renewConsent();
            }
            Log::info('custom_script.deleted', ['script_id' => $script->id, 'actor_id' => auth()->id()]);
        });
    }
}
