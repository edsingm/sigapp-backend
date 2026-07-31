<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'template_id' => ['required', 'integer', 'exists:report_templates,id'],
            'frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'format' => ['sometimes', 'string', Rule::in(['csv', 'xlsx', 'pdf'])],
            'filters' => ['sometimes', 'array', 'max:20'],
            'notify_email' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
