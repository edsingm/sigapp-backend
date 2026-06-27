<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RecalculateAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        $terreno = Terreno::find($id);

        return $terreno instanceof Terreno && Gate::allows('update', $terreno);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
