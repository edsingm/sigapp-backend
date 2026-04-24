<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class UploadKmzRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $terrenoId = $this->route('terreno') ?? $this->route('id');
        $terreno = $terrenoId instanceof Terreno ? $terrenoId : Terreno::find($terrenoId);

        return $user !== null
            && $terreno instanceof Terreno
            && $user->can('update', $terreno);
    }

    public function rules(): array
    {
        return [
            // Validação de MIME omitida intencionalmente: KMZ é um ZIP e seu MIME
            // (application/zip) não é reconhecido de forma confiável pelo Laravel.
            // A validação de extensão e conteúdo é feita pelo KmzParserService.
            'arquivo' => ['required', 'file', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.required' => 'O arquivo é obrigatório.',
            'arquivo.file' => 'O campo arquivo deve conter um arquivo válido.',
            'arquivo.max' => 'O arquivo não pode ser maior que 5 MB.',
        ];
    }
}
