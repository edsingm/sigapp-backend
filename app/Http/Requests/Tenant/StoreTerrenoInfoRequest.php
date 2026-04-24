<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class StoreTerrenoInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $terrenoId = $this->route('terreno') ?? $this->route('id');
        $terreno = $terrenoId instanceof Terreno ? $terrenoId : Terreno::find($terrenoId);

        return $user !== null
            && $terreno instanceof Terreno
            && $user->can('update', $terreno);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string'],
        ];
    }
}
