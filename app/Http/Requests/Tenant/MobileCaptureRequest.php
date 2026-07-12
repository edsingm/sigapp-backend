<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class MobileCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Terreno::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid'],
            'base_version' => ['sometimes', 'integer', 'min:1'],
            'payload' => ['sometimes', 'array'],
            'payload.nome' => ['sometimes', 'string', 'max:255'],
            'payload.endereco' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payload.estado' => ['sometimes', 'nullable', 'string', 'size:2'],
            'payload.cidade_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payload.observacoes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'payload.cep' => ['sometimes', 'nullable', 'string', 'max:10'],
            'payload.bairro' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'array'],
            'location.latitude' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required_with:location', 'numeric', 'between:-180,180'],
            'location.accuracy' => ['nullable', 'numeric', 'min:0'],
            'location.captured_at' => ['nullable', 'date'],
        ];
    }
}
