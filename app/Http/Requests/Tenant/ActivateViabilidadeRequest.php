<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Http\FormRequest;

class ActivateViabilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $viabilidadeId = $this->route('viabilidade') ?? $this->route('id');
        $viabilidade = $viabilidadeId instanceof Viabilidade ? $viabilidadeId : Viabilidade::find($viabilidadeId);

        return $user !== null
            && $viabilidade instanceof Viabilidade
            && $user->can('ativar', $viabilidade);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
