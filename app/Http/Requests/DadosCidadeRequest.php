<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DadosCidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cityCode' => ['required', 'string', 'max:20'],
        ];
    }
}
