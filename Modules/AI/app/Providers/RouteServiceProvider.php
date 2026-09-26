<?php

namespace Modules\AI\app\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     */
    protected string $moduleNamespace = 'Modules\AI\app\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        $this->configureAiChatRateLimiting();

        parent::boot();
    }

    /**
     * Rate limiters for the AI chat endpoints.
     *
     * `send` triggers a paid LLM call, so it's throttled to prevent cost abuse.
     * In DEMO mode the per-IP hit-limit in AiChatController (10 messages, with a
     * friendly "demo restriction" message) is the real cap — so these throttles
     * are switched OFF there. Otherwise Laravel's generic "Too Many Attempts"
     * (429) fires first and masks the demo message.
     */
    protected function configureAiChatRateLimiting(): void
    {
        $isDemo = fn (): bool => function_exists('getEnvMode') && getEnvMode() === 'demo';

        RateLimiter::for('ai-chat-send', function (Request $request) use ($isDemo) {
            return $isDemo()
                ? Limit::none()
                : Limit::perMinute(12)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('ai-chat-group', function (Request $request) use ($isDemo) {
            return $isDemo()
                ? Limit::none()
                : Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
        });
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AI', '/routes/web.php'));

        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AI', '/routes/admin/routes.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
        protected function mapApiRoutes()
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AI', '/routes/api/v1/api.php'));
    }
}
