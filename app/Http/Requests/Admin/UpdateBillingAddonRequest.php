<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Common\BillingAddonType;
use App\Enums\Common\EntitlementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $addonId = (int) $this->route('billing_addon');

        return [
            'slug' => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('billing_addons', 'slug')->ignore($addonId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', Rule::enum(BillingAddonType::class)],
            'stripe_price_id' => ['nullable', 'string', 'max:255', 'regex:/^price_[A-Za-z0-9]+$/'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:brl'],
            'billing_interval' => ['sometimes', 'string', 'in:month'],
            'definition' => ['sometimes', 'array'],
            'definition.grants' => ['required_with:definition', 'array', 'min:1'],
            'definition.grants.*.key' => ['required_with:definition', 'string', 'max:100'],
            'definition.grants.*.type' => ['required_with:definition', Rule::enum(EntitlementType::class)],
            'definition.grants.*.unit_value' => ['required_with:definition'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
