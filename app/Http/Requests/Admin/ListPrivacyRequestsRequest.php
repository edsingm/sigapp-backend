<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PrivacyRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPrivacyRequestsRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(PrivacyRequestStatus::class)],
            'tenant_id' => ['sometimes', 'string'],
            'subject_email' => ['sometimes', 'email'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
