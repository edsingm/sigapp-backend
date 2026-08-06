<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminMfaRotateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->is_admin && $user->admin_mfa_confirmed_at !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:32'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function passedValidation(): void
    {
        $code = $this->validated('code');
        $recoveryCode = $this->validated('recovery_code');

        if (($code === null) === ($recoveryCode === null)) {
            throw new HttpResponseException(ApiResponseService::validationError([
                'code' => ['Informe exatamente um código TOTP ou recovery code.'],
            ]));
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponseService::validationError($validator->errors()->toArray()));
    }
}
