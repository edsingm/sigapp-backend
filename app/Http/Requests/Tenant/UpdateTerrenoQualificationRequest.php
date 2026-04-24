<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTerrenoQualificationRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'urbanistic_preliminary' => ['nullable', 'array'],
            'commercial' => ['nullable', 'array'],
            'desired_product' => ['nullable', 'array'],
            'preliminary_risks' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array'],
            'mark_as_completed' => ['nullable', 'boolean'],
        ];
    }
}
