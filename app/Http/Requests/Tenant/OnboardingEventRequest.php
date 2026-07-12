<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'uuid'],
            'event' => ['required', 'string', 'max:80', 'in:profile_viewed,terrain_started,terrain_created,dashboard_viewed,first_task_created,onboarding_step_completed'],
            'step_id' => ['sometimes', 'string', 'max:80'],
            'metadata' => ['sometimes', 'array', 'max:20'],
            'occurred_at' => ['sometimes', 'date'],
        ];
    }
}
