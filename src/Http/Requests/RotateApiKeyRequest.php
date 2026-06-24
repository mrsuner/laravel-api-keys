<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RotateApiKeyRequest extends FormRequest
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
        return [
            'overlap_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
        ];
    }
}
