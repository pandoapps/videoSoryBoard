<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiKeysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'anthropic' => ['nullable', 'string', 'min:10'],
            'nano_banana' => ['nullable', 'string', 'min:10'],
            'kling_access_key' => ['nullable', 'string', 'min:5'],
            'kling_secret_key' => ['nullable', 'string', 'min:5'],
        ];
    }
}
