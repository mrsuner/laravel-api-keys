<?php

namespace NrmlCo\LaravelApiKeys;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $with = ['user'];

    protected $fillable = [
        'user_id', 'type', 'api_key', 'name',
    ];

    public function user()
    {
        return $this->belongsTo(config('api_key.user_model', 'App\Models\User'));
    }
}
