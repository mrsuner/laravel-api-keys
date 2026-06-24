<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Laravel\Sanctum\SanctumServiceProvider;
use Mrsuner\ApiKeys\ApiKeyServiceProvider;
use Mrsuner\ApiKeys\Tests\Fixtures\IpWhitelistStub;
use Mrsuner\ApiKeys\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            ApiKeyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Sanctum auth wired to the test User model.
        $config->set('auth.guards.sanctum', ['driver' => 'sanctum', 'provider' => 'users']);
        $config->set('auth.providers.users.driver', 'eloquent');
        $config->set('auth.providers.users.model', User::class);

        // Point the package at the test user model.
        $config->set('api-keys.user_model', User::class);

        // Array cache so RateLimiter buckets are isolated per test and honour
        // time travel.
        $config->set('cache.default', 'array');

        // Admin IP whitelist off by default (opted into per-test).
        $config->set('boilerplate.admin.ip_whitelist.enabled', false);
        $config->set('boilerplate.admin.ip_whitelist.cidrs', ['100.64.0.0/10']);

        // Admin route stack: IP stub + sanctum auth + admin ability (no throttle).
        $config->set('api-keys.admin_route.middleware', [
            IpWhitelistStub::class,
            'auth:sanctum',
            'ability:admin',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Users table for the suite; the package's own migrations are
        // auto-loaded by ApiKeyServiceProvider.
        $this->loadMigrationsFrom(__DIR__.'/migrations');

        // Sanctum's personal_access_tokens table (used by the fall-through test).
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/sanctum/database/migrations');
    }

    /**
     * Protected probe routes used by the authentication / ability / rate-limit
     * feature tests. They opt into the package middleware exactly as a host app
     * would.
     */
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->middleware(['api.key', 'auth:sanctum'])->get('/test/me', function (Request $request) {
            return response()->json(['user_id' => $request->user()->getKey()]);
        });

        $router->middleware(['api.key', 'auth:sanctum', 'api.ability:read:orders'])
            ->get('/test/orders', fn () => response()->json(['ok' => true]));
    }
}
