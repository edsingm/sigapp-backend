<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'integer', 'exists:report_templates,id'],
            'idempotency_key' => ['required', 'uuid'],
            'format' => ['sometimes', 'string', Rule::in(['csv'])],
            'filters' => ['sometimes', 'array', 'max:20'],
            'filters.status' => ['sometimes', 'string', 'max:80'],
            'filters.estado' => ['sometimes', 'string', 'size:2'],
            'filters.date_from' => ['sometimes', 'date'],
            'filters.date_to' => ['sometimes', 'date', 'after_or_equal:filters.date_from'],
        ];
    }
}
