<?php

namespace App\Providers;

use App\Http\Middleware\ApplySiteSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
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
