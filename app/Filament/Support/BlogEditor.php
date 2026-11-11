<?php

namespace App\Filament\Support;

use App\Models\Post;
use App\Models\Redirect;
use App\Models\User;
use App\Services\InternalUrl;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogEditor
{
    public static function title(string $name, string $label): TextInput
    {
        return ContentFields::title($name, $label)->clearAfterStateUpdatedHooks()
            ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set, string $operation): void {
                if ($operation === 'create' && (blank($get('slug')) || $get('slug') === Str::slug($old ?? ''))) {
                    $set('slug', Str::slug($state ?? ''));
                }
            });
    }

    public static function authorize(string $ability, Model|string $record): void
    {
        $actor = auth()->id() ? User::query()->find(auth()->id()) : null;
        abort_unless($actor, 403);
        Gate::forUser($actor)->authorize($ability, $record);
    }

    public static function rethrowWriteConflict(QueryException $exception): never
    {
        $sql = strtolower($exception->getSql());
        if ($exception instanceof UniqueConstraintViolationException && preg_match('/(?:posts|post_categories|redirects)/', $sql)) {
            throw ValidationException::withMessages(['data.slug' => 'Esta URL ya está en uso. Revisa el slug e inténtalo de nuevo.']);
        }
        $foreignKeyConflict = in_array((int) ($exception->errorInfo[1] ?? 0), [1451, 1452], true)
            || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed');
        if ($foreignKeyConflict && preg_match('/(?:posts|post_categories|post_category_post)/', $sql)) {
            $field = str_contains($sql, 'post_category_post') ? 'categories' : (str_contains($sql, 'post_categories') ? 'name' : 'image_id');
            throw ValidationException::withMessages(['data.'.$field => 'La categoría o imagen cambió mientras editabas. Revisa la selección e inténtalo de nuevo.']);
        }

        throw $exception;
    }

    public static function revision(Model $record): string
    {
        $attributes = $record->getAttributes();
        ksort($attributes);
        $categories = $record instanceof Post ? $record->categories->modelKeys() : [];
        sort($categories);

        return hash('sha256', json_encode([$attributes, $categories], JSON_THROW_ON_ERROR));
    }

    public static function lockForEditing(Model $record, string $revision): Model
    {
        $current = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->first();
        $field = $record instanceof Post ? 'title' : 'name';
        if (! $current || ! hash_equals($revision, static::revision($current))) {
            throw ValidationException::withMessages(['data.'.$field => 'Este contenido cambió o fue eliminado en otra sesión. Conserva tus cambios y recarga antes de volver a guardar.']);
        }
        static::authorize('update', $current);

        return $current;
    }

    public static function validatePostSlug(string $slug): void
    {
        if (Redirect::query()->where('source', '/blog/'.$slug)->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['data.slug' => 'Esta URL está reservada por una redirección existente. Elige otro slug.']);
        }
    }

    public static function preservePostUrl(Post $post, string $oldSlug): void
    {
        $source = '/blog/'.$oldSlug;
        $destination = '/blog/'.$post->slug;
        if (! InternalUrl::path($source) || ! InternalUrl::path($destination)
            || Redirect::query()->where('source', $source)->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['data.slug' => 'No se puede conservar la URL anterior de forma segura. Revisa el slug y las redirecciones existentes.']);
        }
        try {
            Redirect::query()->create(['source' => $source, 'destination' => $destination, 'status_code' => 301, 'is_active' => true]);
            foreach (Redirect::query()->where('destination', $source)->where('is_active', true)->lockForUpdate()->get() as $redirect) {
                $redirect->update(['destination' => $destination]);
            }
        } catch (ValidationException) {
            throw ValidationException::withMessages(['data.slug' => 'El cambio crea un conflicto de redirecciones. Elige otra URL o revisa las redirecciones existentes.']);
        }
    }

    public static function delete(Model $record, string $revision): bool
    {
        $current = static::lockForEditing($record, $revision);
        static::authorize('delete', $current);
        try {
            return (bool) $current->delete();
        } catch (QueryException $exception) {
            try {
                static::rethrowWriteConflict($exception);
            } catch (ValidationException) {
                Notification::make()->danger()->title('No se pudo eliminar')
                    ->body('El contenido tiene referencias. Recarga y revisa sus relaciones antes de eliminarlo.')->send();

                return false;
            }
        }
    }
}
