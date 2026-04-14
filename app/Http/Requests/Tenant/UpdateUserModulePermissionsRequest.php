<?php

namespace App\Http\Requests\Tenant;

use App\Enums\AccessLevel;
use App\Enums\Common\ModulesEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserModulePermissionsRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validModules = array_column(ModulesEnum::cases(), 'value');
        $validLevels = array_column(AccessLevel::cases(), 'value');

        $rules = [
            'permissions' => ['required', 'array'],
        ];

        foreach (ModulesEnum::cases() as $module) {
            $key = "permissions.{$module->value}";

            if ($module->hasSubmodules()) {
                $rules[$key] = ['sometimes', 'array'];

                foreach ($module->submodules() as $resource) {
                    $rules["{$key}.{$resource}"] = [
                        'sometimes',
                        'nullable',
                        Rule::in($validLevels),
                    ];
                }
            } else {
                $rules[$key] = [
                    'sometimes',
                    'nullable',
                    Rule::in($validLevels),
                ];
            }
        }

        return $rules;
    }

    /**
     * Obtém as mensagens de erro para as regras de validação definidas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.required' => 'O campo permissions é obrigatório.',
            'permissions.array' => 'O campo permissions deve ser um objeto.',
        ];
    }
}
