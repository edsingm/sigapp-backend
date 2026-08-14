<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\User;
use App\Services\Privacy\LegalDocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLegalAcceptanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return tenancy()->initialized && $user instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $knownKeys = app(LegalDocumentService::class)->documentKeys();

        return [
            'document_keys' => ['sometimes', 'nullable', 'array', 'min:1'],
            'document_keys.*' => ['required', 'string', Rule::in($knownKeys)],
        ];
    }
}
