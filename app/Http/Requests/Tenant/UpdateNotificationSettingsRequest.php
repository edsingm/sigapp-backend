<?php

namespace App\Http\Requests\Tenant;

use App\Services\Tenant\NotificationPreferenceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'email_digest_frequency' => ['sometimes', 'string', Rule::in(NotificationPreferenceService::DIGEST_FREQUENCIES)],
        ];
    }
}
