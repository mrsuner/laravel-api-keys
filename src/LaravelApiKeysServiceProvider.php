<?php

namespace NrmlCo\LaravelApiKeys;

use Illuminate\Support\ServiceProvider;

class LaravelApiKeysServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/config.php'   =>  config_path('api_key.php'),            
        ]);

        $this->publishes([
            __DIR__ . '/../database/migrations/2019_01_01_000000_create_api_keys_table.php' => database_path('migrations/2019_01_01_000000_create_api_keys_table.php'),
        ], 'laravel-api-key-migration');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Register the main class to use with the facade
        $this->app->singleton('laravel-api-keys', function () {
            return new LaravelApiKeys();
        });

        auth()->extend('api_key', function ($app, $name, array $config) {

            // automatically build the DI, put it as reference
            $userProvider = app(ApiKeyToUserProvider::class);
            $request = app('request');

            return new ApiKeyGuard($userProvider, $request, $config);
        });
    }
}
