<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'terreno_id' => ['nullable', 'integer', 'exists:terrenos,id'],
            'related_type' => ['nullable', 'string', Rule::in(['terreno', 'viabilidade', 'comite', 'negociacao', 'legalizacao', 'projeto'])],
            'related_id' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', Rule::in(['open', 'pendente', 'pending', 'in_progress', 'em_andamento', 'blocked'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'baixa', 'normal', 'medium', 'media', 'high', 'alta', 'urgent'])],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
