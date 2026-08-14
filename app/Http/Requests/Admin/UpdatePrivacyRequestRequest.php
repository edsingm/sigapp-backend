<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PrivacyRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrivacyRequestRequest extends FormRequest
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
            'legal_hold_reason' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'export_path' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
