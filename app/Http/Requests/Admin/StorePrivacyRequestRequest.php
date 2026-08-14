<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PrivacyRequestKind;
use App\Enums\PrivacySubjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrivacyRequestRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(PrivacyRequestKind::class)],
            'subject_type' => ['required', Rule::enum(PrivacySubjectType::class)],
            'subject_email' => ['required', 'email', 'max:255'],
            'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
            'notes' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
