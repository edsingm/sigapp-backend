<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Documento;
use Illuminate\Foundation\Http\FormRequest;

class DocumentAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', Documento::find($this->route('documento'))) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
