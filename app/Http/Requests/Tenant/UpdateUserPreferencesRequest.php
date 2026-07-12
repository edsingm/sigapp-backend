<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'theme' => ['sometimes', 'nullable', 'string', Rule::in(['system', 'light', 'dark'])],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(['pt-br', 'en-us'])],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            'density' => ['sometimes', 'nullable', 'string', 'in:comfortable,compact,dense'],
            'dashboard_layout' => ['sometimes', 'nullable', 'string', 'max:100'],
            'favorites' => ['sometimes', 'array', 'max:50'],
            'favorites.*.id' => ['required'],
            'favorites.*.type' => ['required', 'string'],
            'recent' => ['sometimes', 'array', 'max:50'],
            'recent.*.id' => ['required'],
            'recent.*.type' => ['required', 'string'],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.*.key' => ['required', 'string'],
            'notification_preferences.*.channels' => ['array'],
            'notification_preferences.*.channels.*.channel' => ['required', 'string'],
            'notification_preferences.*.channels.*.enabled' => ['required', 'boolean'],
            'notification_settings' => ['sometimes', 'nullable', 'array'],
            'notification_settings.quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'notification_settings.quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'notification_settings.email_digest_frequency' => ['nullable', 'string', 'in:instant,daily,weekly'],
        ];
    }
}
