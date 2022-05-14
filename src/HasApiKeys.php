<?php

namespace NrmlCo\LaravelApiKeys;

trait HasApiKeys
{
    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * create a new api key
     *
     * @param string|null $apiKeyType
     * @param array $data
     * @return void
     */
    protected function createApiKey(string $apiKeyType = null, array $data = []){
        if ($apiKeyType === null) {
            $apiKeyType = ApiKeyType::SANDBOX;
        }

        $name = isset($data['name']) ? $data['name'] : 'Unnamed Key';

        $model = config('api_key.api_key_model', null);
        if($model === null) {
            $model = ApiKey::class;
        }
        return $model::firstOrCreate([
            'user_id' => $this->id,
            'type' => $apiKeyType,
            'api_key' => Str::random(60),
            'name' => $name,
        ]);
    }
}
