<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsentLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consent_id'              => ['required', 'uuid'],
            'categories'              => ['required', 'array'],
            'categories.functional'   => ['required', 'boolean'],
            'categories.analytics'    => ['required', 'boolean'],
            'categories.marketing'    => ['required', 'boolean'],
            'version'                 => ['required', 'string', 'max:10'],
            'timestamp'               => ['required', 'date'],
        ];
    }
}
