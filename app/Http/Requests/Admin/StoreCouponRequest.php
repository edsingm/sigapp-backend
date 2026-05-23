<?php

namespace App\Http\Requests\Admin;

use App\Services\ApiResponseService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:percent,fixed'],
            'percent_off' => ['required_if:type,percent', 'integer', 'min:1', 'max:100'],
            'amount_off' => ['required_if:type,fixed', 'integer', 'min:1'],
            'currency' => ['required_if:type,fixed', 'string', 'size:3'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'redeem_by' => ['nullable', 'date', 'after:now'],
            'expires_after_first_redemption' => ['boolean'],
            'applies_to_plans' => ['nullable', 'array'],
            'applies_to_plans.*' => ['string'],
            'applies_to_tenants' => ['nullable', 'array'],
            'applies_to_tenants.*' => ['string'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponseService::validationError($validator->errors()->toArray())
        );
    }
}
