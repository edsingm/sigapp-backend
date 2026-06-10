<?php

namespace App\Http\Requests;

use App\Services\LanguageService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(LanguageService::SUPPORTED_LOCALES)],
        ];
    }
}
