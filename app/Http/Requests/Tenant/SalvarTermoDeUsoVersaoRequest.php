<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class SalvarTermoDeUsoVersaoRequest extends FormRequest
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
