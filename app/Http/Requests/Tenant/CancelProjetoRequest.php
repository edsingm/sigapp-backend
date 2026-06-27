<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelProjetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        $projeto = Projeto::find($id);

        return $projeto instanceof Projeto && Gate::allows('cancel', $projeto);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
