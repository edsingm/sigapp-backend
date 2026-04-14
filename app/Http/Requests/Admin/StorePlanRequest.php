<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:plans,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_popular' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
