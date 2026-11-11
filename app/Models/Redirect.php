<?php

namespace App\Models;

use App\Services\InternalUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Fillable(['source', 'destination', 'status_code', 'is_active'])]
class Redirect extends Model
{
    use HasFactory;

    protected $attributes = ['status_code' => 301, 'is_active' => true];

    protected function casts(): array
    {
        return ['status_code' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $redirect): void {
            $redirect->source = InternalUrl::path($redirect->source) ?? $redirect->source;
            $redirect->destination = InternalUrl::path($redirect->destination) ?? $redirect->destination;
            $errors = [];
            if (! InternalUrl::path($redirect->source) || InternalUrl::protectedPath($redirect->source) || static::hasRouteConflict($redirect->source)) {
                $errors['source'] = 'Usa una ruta interna sin parámetros que no coincida con una ruta existente o reservada.';
            }
            if (! InternalUrl::path($redirect->destination) || InternalUrl::protectedPath($redirect->destination)) {
                $errors['destination'] = 'Usa una ruta interna pública, sin dominio ni parámetros.';
            }
            if (! in_array($redirect->status_code, [301, 302], true)) {
                $errors['status_code'] = 'Solo se permiten 301 y 302.';
            }
            if ($redirect->source === $redirect->destination || ($redirect->is_active && ! $redirect->terminalDestination())) {
                $errors['destination'] = 'La redirección crea un ciclo o una cadena demasiado larga.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        });
    }

    public static function hasRouteConflict(string $path): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create(InternalUrl::absolute($path), 'GET'));

            return match ($route->getName()) {
                'posts.show' => Post::query()->where('slug', $route->parameter('slug'))->exists(),
                'services.show' => Service::query()->where('slug', $route->parameter('slug'))->exists(),
                'projects.show' => Project::query()->where('slug', $route->parameter('slug'))->exists(),
                'tools.show', 'tools.alternatives' => Tool::query()->where('slug', $route->parameter('slug'))->exists(),
                'workflows.show' => Workflow::query()->where('slug', $route->parameter('slug'))->exists(),
                'templates.show' => Template::query()->where('slug', $route->parameter('slug'))->exists(),
                default => true,
            };
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }
    }

    public function terminalDestination(): ?string
    {
        $visited = [$this->source];
        $destination = $this->destination;
        for ($depth = 0; $depth < 20; $depth++) {
            if (! InternalUrl::path($destination) || InternalUrl::protectedPath($destination) || in_array($destination, $visited, true)) {
                return null;
            }
            $visited[] = $destination;
            $next = static::query()->where('source', $destination)->where('is_active', true)->whereKeyNot($this->id ?? 0)->first();
            if (! $next || static::hasRouteConflict($destination)) {
                return $destination;
            }
            $destination = $next->destination;
        }

        return null;
    }
}
