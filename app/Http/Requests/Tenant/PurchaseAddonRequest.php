<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'addon_slug' => ['required', 'string', 'max:100', 'alpha_dash', 'exists:billing_addons,slug'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
