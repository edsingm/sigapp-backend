<?php

namespace App\Http\Requests;

use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'locale' => ['sometimes', 'string', Rule::in(LanguageService::SUPPORTED_LOCALES)],
            'password' => ['sometimes', 'confirmed', PasswordRule::defaults()],
        ];
    }
}
