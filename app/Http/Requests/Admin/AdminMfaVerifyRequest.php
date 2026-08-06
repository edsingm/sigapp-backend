<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\ApiResponseService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminMfaVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'challenge' => ['required', 'string', 'size:64'],
            'code' => ['nullable', 'string', 'max:32'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->input('code') === null ? null : trim((string) $this->input('code')),
            'recovery_code' => $this->input('recovery_code') === null ? null : trim((string) $this->input('recovery_code')),
        ]);
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
