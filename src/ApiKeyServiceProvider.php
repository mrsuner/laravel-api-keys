<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Mrsuner\ApiKeys\Console\PruneApiKeysCommand;
use Mrsuner\ApiKeys\Http\Middleware\AuthenticateWithApiKey;
use Mrsuner\ApiKeys\Http\Middleware\CheckApiKeyAbility;
use Mrsuner\ApiKeys\Services\ApiKeyService;

class ApiKeyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-keys.php', 'api-keys');

        $this->app->singleton(ApiKeyService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/api-keys.php' => config_path('api-keys.php'),
        ], 'api-keys-config');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneApiKeysCommand::class]);
        }

        $this->registerMiddlewareAliases();
        $this->registerUserRoutes();
        $this->registerAdminRoutes();
    }

    /**
     * Register the package's own middleware aliases (api.key / api.ability) so
     * routes can opt in without the host editing bootstrap/app.php. Also alias
     * Sanctum's ability check to `ability` when the host hasn't already.
     */
    private function registerMiddlewareAliases(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $aliases = $router->getMiddleware();

        if (! array_key_exists('api.key', $aliases)) {
            $router->aliasMiddleware('api.key', AuthenticateWithApiKey::class);
        }

        if (! array_key_exists('api.ability', $aliases)) {
            $router->aliasMiddleware('api.ability', CheckApiKeyAbility::class);
        }

        if (class_exists(CheckForAnyAbility::class) && ! array_key_exists('ability', $aliases)) {
            $router->aliasMiddleware('ability', CheckForAnyAbility::class);
        }
    }

    private function registerUserRoutes(): void
    {
        if (! config('api-keys.user_route.enabled', true)) {
            return;
        }

        $middleware = array_merge(
            (array) config('api-keys.user_route.middleware', ['auth:sanctum']),
            [SubstituteBindings::class],
        );

        Route::prefix(config('api-keys.user_route.prefix', 'v1/api-keys'))
            ->middleware($middleware)
            ->name(config('api-keys.user_route.name', 'api-keys.'))
            ->group(__DIR__.'/../routes/user.php');
    }

    private function registerAdminRoutes(): void
    {
        if (! config('api-keys.admin_route.enabled', true)) {
            return;
        }

        // Respect the boilerplate admin master switch when the host defines it.
        if (! config('boilerplate.admin.enabled', true)) {
            return;
        }

        $middleware = array_merge(
            $this->resolveAdminMiddleware(),
            [SubstituteBindings::class],
        );

        Route::prefix(config('api-keys.admin_route.prefix', 'internal/admin/v1'))
            ->middleware($middleware)
            ->name(config('api-keys.admin_route.name', 'admin.api-keys.'))
            ->group(__DIR__.'/../routes/admin.php');
    }

    /**
     * Resolve the admin middleware stack.
     *
     * An explicit `api-keys.admin_route.middleware` array always wins. When it
     * is null (the default), the stack is auto-detected: the full boilerplate
     * admin stack when its IP-whitelist middleware is present, otherwise a
     * minimal Sanctum-authenticated stack so the package works in any app.
     *
     * @return array<int, string>
     */
    private function resolveAdminMiddleware(): array
    {
        $configured = config('api-keys.admin_route.middleware');

        if (is_array($configured)) {
            return $configured;
        }

        $boilerplateIpWhitelist = 'App\\Http\\Middleware\\InternalIpWhitelist';
        $boilerplateAdminAccess = 'App\\Http\\Middleware\\EnsureAdminAccess';

        if (class_exists($boilerplateIpWhitelist)) {
            $middleware = [
                'throttle:60,1',
                $boilerplateIpWhitelist,
                'auth:sanctum',
                'ability:admin',
            ];

            if (class_exists($boilerplateAdminAccess)) {
                $middleware[] = $boilerplateAdminAccess;
            }

            return $middleware;
        }

        return ['auth:sanctum', 'ability:admin'];
    }
}
