<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Documento;
use Illuminate\Foundation\Http\FormRequest;

class DocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Documento::find($this->route('documento'))) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['arquivo' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,kml,kmz,dwg']];
    }
}
