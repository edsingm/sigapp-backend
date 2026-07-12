<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ListDocumentRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:terreno,viabilidade,comite,legalizacao,projeto'],
            'entity_id' => ['sometimes', 'integer', 'min:1'],
            'phase' => ['sometimes', 'string', 'max:60'],
        ];
    }
}
