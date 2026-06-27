<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GerarDreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        $viabilidade = Viabilidade::find($id);

        return $viabilidade instanceof Viabilidade && Gate::allows('gerarDre', $viabilidade);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
