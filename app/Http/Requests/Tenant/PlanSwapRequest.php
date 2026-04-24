<?php

namespace App\Http\Requests\Tenant;

use App\Services\ApiResponseService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PlanSwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
            'prorate' => ['sometimes', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponseService::validationError($validator->errors()->toArray())
        );
    }
}
