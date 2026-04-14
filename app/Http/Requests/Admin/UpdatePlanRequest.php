<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        $planId = (int) $this->route('plan');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($planId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_popular' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
