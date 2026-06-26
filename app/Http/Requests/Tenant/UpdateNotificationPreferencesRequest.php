<?php

namespace App\Http\Requests\Tenant;

use App\Notifications\NotificationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
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
        $channels = [
            NotificationCatalog::CHANNEL_EMAIL,
            NotificationCatalog::CHANNEL_IN_APP,
            NotificationCatalog::CHANNEL_PUSH,
        ];

        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.category' => ['required', 'string', Rule::in(array_keys(NotificationCatalog::categories()))],
            'preferences.*.channel' => ['required', 'string', Rule::in($channels)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
