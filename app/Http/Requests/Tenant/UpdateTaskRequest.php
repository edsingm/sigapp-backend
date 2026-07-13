<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user()?->isAdmin()
            || (($task instanceof Task) && $this->user()?->can('viewAny', Terreno::class));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'related_type' => ['sometimes', 'nullable', 'string', Rule::in(['terreno', 'viabilidade', 'comite', 'negociacao', 'legalizacao', 'projeto'])],
            'related_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(['open', 'pendente', 'pending', 'in_progress', 'em_andamento', 'concluded', 'concluida', 'completed', 'cancelled', 'cancelada', 'blocked'])],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'baixa', 'normal', 'medium', 'media', 'high', 'alta', 'urgent'])],
            'tags' => ['sometimes', 'nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'due_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
