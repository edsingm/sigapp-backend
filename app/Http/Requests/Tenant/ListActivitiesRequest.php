<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListActivitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Terreno::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity_type' => ['nullable', 'string', Rule::in([
                'terreno',
                'viabilidade',
                'comite',
                'negociacao',
                'contrato',
                'legalizacao',
                'legalizacao_etapa',
                'projeto',
                'documento',
                'relatorio',
            ])],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'actor_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in([
                'created',
                'updated',
                'commented',
                'status_changed',
                'mentioned',
                'assigned',
                'approved',
                'rejected',
                'generated',
            ])],
            'occurred_after' => ['nullable', 'date'],
            'occurred_before' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
