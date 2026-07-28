<?php

namespace App\Http\Requests\Admin;

use App\Models\Central\PlatformAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformAnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'channel' => [
                'required',
                'string',
                Rule::in([
                    PlatformAnnouncement::CHANNEL_EMAIL,
                    PlatformAnnouncement::CHANNEL_BANNER,
                    PlatformAnnouncement::CHANNEL_BOTH,
                ]),
            ],
            'segment' => [
                'required',
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $segment = $this->input('segment');
            $value = $this->input('segment_value');
            if (in_array($segment, [
                PlatformAnnouncement::SEGMENT_PLAN,
                PlatformAnnouncement::SEGMENT_STATUS,
            ], true) && ($value === null || $value === '')) {
                $validator->errors()->add('segment_value', 'Informe o valor do segmento.');
            }
        });
    }
}
