<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(['open', 'pendente', 'pending', 'in_progress', 'em_andamento', 'concluded', 'concluida', 'completed', 'cancelled', 'cancelada', 'blocked'])],
            'priority' => ['nullable', 'array'],
            'priority.*' => ['string', Rule::in(['low', 'baixa', 'normal', 'medium', 'media', 'high', 'alta', 'urgent'])],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'related_entity_type' => ['nullable', 'string', Rule::in(['terreno', 'viabilidade', 'comite', 'negociacao', 'legalizacao', 'projeto'])],
            'related_entity_id' => ['nullable', 'integer', 'min:1'],
            'due_before' => ['nullable', 'date'],
            'due_after' => ['nullable', 'date'],
            'overdue' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
