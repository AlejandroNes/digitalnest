<?php

namespace App\Providers;

use App\Http\Middleware\ApplySiteSettings;
use App\Models\Faq;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Starter-kit models hidden when the corporate modules are disabled.
     *
     * @var list<class-string>
     */
    private const CORPORATE_MODELS = [Service::class, Testimonial::class, Faq::class, Project::class, ProjectCategory::class];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([ApplySiteSettings::class]);

        Gate::before(function (?User $user, string $ability, array $arguments): ?bool {
            $subject = $arguments[0] ?? null;
            $class = is_object($subject) ? $subject::class : $subject;

            return is_string($class) && in_array($class, self::CORPORATE_MODELS, true) && ! config('features.corporate_modules_enabled')
                ? false
                : null;
        });

        RateLimiter::for('contact', fn (Request $request): array => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);

        Password::defaults(fn (): Password => Password::min(12)->mixedCase()->numbers()->symbols());

        Blade::directive('settings', function (string $expression): string {
            return "<?php echo e(\App\Models\GeneralSetting::current()->getAttribute({$expression})); ?>";
        });
    }
}
