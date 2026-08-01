<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Services\Tenant\ReportCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxColumns = ReportCatalogService::DETAIL_COLUMNS_MAX;

        return [
            'name' => ['required', 'string', 'max:150'],
            'scope' => ['sometimes', 'string', Rule::in(['private', 'shared'])],
            'definition' => ['required', 'array'],
            'definition.mode' => ['sometimes', 'string', Rule::in(['aggregate', 'detail'])],
            'definition.datasets' => ['required', 'array', 'min:1', 'max:4'],
            'definition.datasets.*' => ['required', 'string'],
            'definition.dimensions' => ['sometimes', 'array', 'min:1', 'max:4'],
            'definition.dimensions.*' => ['required', 'string'],
            'definition.metrics' => ['sometimes', 'array', 'min:1', 'max:4'],
            'definition.metrics.*' => ['required', 'string'],
            'definition.columns' => ['sometimes', 'array', 'min:1', 'max:'.$maxColumns],
            'definition.columns.*' => ['required', 'string'],
            'definition.charts' => ['sometimes', 'array', 'max:3'],
            'definition.charts.*' => ['string'],
        ];
    }
}
