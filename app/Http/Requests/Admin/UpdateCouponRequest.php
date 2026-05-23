<?php

namespace App\Http\Requests\Admin;

use App\Services\ApiResponseService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCouponRequest extends FormRequest
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
        $couponId = $this->route('coupon');
        $couponId = is_numeric($couponId) ? (int) $couponId : 0;

        return [
            'code' => ['sometimes', 'string', 'max:50', 'alpha_dash', 'unique:coupons,code,'.$couponId],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'redeem_by' => ['nullable', 'date'],
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
