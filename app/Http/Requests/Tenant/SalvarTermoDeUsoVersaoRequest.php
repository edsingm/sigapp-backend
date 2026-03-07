<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class SalvarTermoDeUsoVersaoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conteudo' => ['required', 'string', 'min:20'],
        ];
    }

    /**
     * Mensagens de validação em pt-br.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conteudo.required' => 'O conteúdo do termo é obrigatório.',
            'conteudo.string' => 'O conteúdo do termo deve ser um texto.',
            'conteudo.min' => 'O conteúdo do termo deve ter pelo menos :min caracteres.',
        ];
    }
}
