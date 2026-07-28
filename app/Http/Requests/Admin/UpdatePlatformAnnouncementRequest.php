<?php

namespace App\Http\Requests\Admin;

use App\Models\Central\PlatformAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    /**
     * @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'body' => ['sometimes', 'string', 'max:10000'],
            'type' => [
                'sometimes',
                'string',
                Rule::in(PlatformAnnouncement::types()),
            ],
            'channel' => [
                'sometimes',
                'string',
                Rule::in([
                    PlatformAnnouncement::CHANNEL_EMAIL,
                    PlatformAnnouncement::CHANNEL_BANNER,
                    PlatformAnnouncement::CHANNEL_BOTH,
                ]),
            ],
            'segment' => [
                'sometimes',
                'string',
                Rule::in([
                    PlatformAnnouncement::SEGMENT_ALL,
                    PlatformAnnouncement::SEGMENT_PLAN,
                    PlatformAnnouncement::SEGMENT_STATUS,
                ]),
            ],
            'segment_value' => ['nullable', 'string', 'max:100'],
        ];
    }
}
