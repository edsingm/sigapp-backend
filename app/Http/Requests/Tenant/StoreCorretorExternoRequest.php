<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CorretorExterno;
use App\Repositories\Tenant\CorretorExternoRepository;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreCorretorExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CorretorExterno::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = CorretorExterno::rules();
        $rules['email'] = [
            'required',
            'email',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && app(CorretorExternoRepository::class)->emailExists($value)) {
                    $fail(__('CORRETOR_EMAIL_ALREADY_USED'));
                }
            },
        ];

        return $rules;
    }

    public function messages(): array
    {
        return CorretorExterno::messages();
    }
}
