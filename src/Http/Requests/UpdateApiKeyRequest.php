<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // environment is intentionally absent — it is immutable after creation.
        return [
            'name'        => ['sometimes', 'string', 'max:100'],
            'abilities'   => ['sometimes', 'nullable', 'array'],
            'abilities.*' => ['string', 'max:64'],
            'rate_limit'  => ['sometimes', 'nullable', 'array'],
            'rate_limit.max_attempts'  => ['required_with:rate_limit', 'integer', 'min:1', 'max:100000'],
            'rate_limit.decay_seconds' => ['required_with:rate_limit', 'integer', 'min:60', 'max:86400'],
            'expires_at'  => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
