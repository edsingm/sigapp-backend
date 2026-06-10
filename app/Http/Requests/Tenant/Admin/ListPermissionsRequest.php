<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyRole(['admin', 'ADMIN', 'director', 'DIRECTOR']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:160'],
        ];
    }
}
