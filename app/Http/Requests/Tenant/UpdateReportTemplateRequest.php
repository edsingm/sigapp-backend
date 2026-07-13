<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

class UpdateReportTemplateRequest extends StoreReportTemplateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => ['sometimes', 'string', 'max:150'],
            'definition' => ['sometimes', 'array'],
            'definition.datasets' => ['sometimes', 'array', 'min:1', 'max:4'],
            'definition.dimensions' => ['sometimes', 'array', 'min:1', 'max:4'],
            'definition.metrics' => ['sometimes', 'array', 'min:1', 'max:4'],
        ]);
    }
}
