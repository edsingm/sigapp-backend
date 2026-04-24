<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Http\FormRequest;

class RestoreViabilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $viabilidadeId = $this->route('viabilidade') ?? $this->route('id');
        $viabilidade = $viabilidadeId instanceof Viabilidade ? $viabilidadeId : Viabilidade::withTrashed()->find($viabilidadeId);

        return $user !== null
            && $viabilidade instanceof Viabilidade
            && $user->can('restore', $viabilidade);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
