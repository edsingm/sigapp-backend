<?php
 
namespace App\Http\Requests\Tenant;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreProprietarioRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'terreno_id' => 'required|integer|exists:terrenos,id',
            'nome' => 'required|string|max:255',
            'rg' => 'nullable|string|max:20',
            'cpf_cnpj' => 'nullable|string|max:20',
            'nascimento' => 'nullable|date',
            'tipo_pessoa' => 'required|string|in:fisica,juridica',
            'estado_civil' => 'nullable|string|max:50',
            'nacionalidade' => 'nullable|string|max:100',
            'profissao' => 'nullable|string|max:100',
            'porcentagem_terreno' => 'nullable|numeric|min:0|max:100',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'conjuge' => 'nullable|string|max:255',
            'conjuge_rg' => 'nullable|string|max:20',
            'conjuge_nascimento' => 'nullable|date',
            'conjuge_cpf_cnpj' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ];
    }
 
    /**
     * Obtém as mensagens personalizadas para erros do validador.
     */
    public function messages(): array
    {
        return [
            'terreno_id.required' => 'O terreno vinculado é obrigatório.',
            'terreno_id.exists' => 'O terreno selecionado não existe.',
            'nome.required' => 'O nome do proprietário é obrigatório.',
            'tipo_pessoa.required' => 'O tipo de pessoa é obrigatório.',
            'tipo_pessoa.in' => 'O tipo de pessoa deve ser física ou jurídica.',
            'email.email' => 'Informe um e-mail válido.',
            'porcentagem_terreno.numeric' => 'A porcentagem deve ser um número.',
        ];
    }
}
