<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Common\BillingAddonType;
use App\Enums\Common\EntitlementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:billing_addons,slug'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(BillingAddonType::class)],
            'stripe_price_id' => ['nullable', 'string', 'max:255', 'regex:/^price_[A-Za-z0-9]+$/'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:brl'],
            'billing_interval' => ['sometimes', 'string', 'in:month,one_time'],
            'definition' => ['required', 'array'],
            'definition.grants' => ['required', 'array', 'min:1'],
            'definition.grants.*.key' => ['required', 'string', 'max:100'],
            'definition.grants.*.type' => ['required', Rule::enum(EntitlementType::class)],
            'definition.grants.*.unit_value' => ['required'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
