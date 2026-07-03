<?php

namespace App\Http\Requests\Tenant;

use App\Enums\Common\RolesEnum;
use Illuminate\Foundation\Http\FormRequest;

class RevokeViabilidadeApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Revogar aprovação é uma ação exclusiva do cargo de Diretor.
        return $user !== null && $user->hasRole(RolesEnum::DIRECTOR->value);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'approval_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
