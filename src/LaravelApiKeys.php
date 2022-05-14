<?php

namespace NrmlCo\LaravelApiKeys;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LaravelApiKeys
{
    public static function create(string $apiKeyType = null,array $data = [])
    {
        if ($apiKeyType === null) {
            $apiKeyType = ApiKeyType::SANDBOX;
        }

        $name = isset($data['name']) ? $data['name'] : 'Unnamed Key';

        $model = config('api_key.api_key_model', null);
        if($model === null) {
            $model = ApiKey::class;
        }
        return $model::firstOrCreate([
            'user_id' => Auth::id(),
            'type' => $apiKeyType,
            'api_key' => Str::random(60),
            'name' => $name,
        ]);
    }

    public static function register()
    {
        auth()->extend('api_key', function ($app, $name, array $config) {

            // automatically build the DI, put it as reference
            $userProvider = app(ApiKeyToUserProvider::class);
            $request = app('request');

            return new ApiKeyGuard($userProvider, $request, $config);
        });
    }
}
