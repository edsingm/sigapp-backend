<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MarkProjetoReadyRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     * A autorização específica do markReady é feita no controller via Gate::authorize('markReady', $projeto).
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Projeto::class);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     */
    public function rules(): array
    {
        return [];
    }
}
