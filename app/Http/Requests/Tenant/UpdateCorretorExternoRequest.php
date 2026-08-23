<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CorretorExterno;
use App\Repositories\Tenant\CorretorExternoRepository;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCorretorExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', CorretorExterno::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $routeId = $this->route('id');
        $id = is_string($routeId) ? $routeId : null;
        $rules = CorretorExterno::rules($id);
        $rules['email'] = [
            'required',
            'email',
            function (string $attribute, mixed $value, Closure $fail) use ($id): void {
                if (is_string($value) && app(CorretorExternoRepository::class)->emailExists($value, $id)) {
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
