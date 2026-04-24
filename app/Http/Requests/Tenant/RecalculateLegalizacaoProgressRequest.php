<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Legalizacao;
use Illuminate\Foundation\Http\FormRequest;

class RecalculateLegalizacaoProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $legalizacaoId = $this->route('legalizacao')
            ?? $this->route('id')
            ?? collect($this->route()?->parameters() ?? [])->first();
        $legalizacao = $legalizacaoId instanceof Legalizacao ? $legalizacaoId : Legalizacao::find($legalizacaoId);

        return $user !== null
            && $legalizacao instanceof Legalizacao
            && $user->can('recalcularProgresso', $legalizacao);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
