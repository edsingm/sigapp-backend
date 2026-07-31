<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'template_id' => ['sometimes', 'integer', 'exists:report_templates,id'],
            'frequency' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'format' => ['sometimes', 'string', Rule::in(['csv', 'xlsx', 'pdf'])],
            'filters' => ['sometimes', 'array', 'max:20'],
            'notify_email' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
