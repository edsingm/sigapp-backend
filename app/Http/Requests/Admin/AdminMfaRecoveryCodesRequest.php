<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;

class AdminMfaRecoveryCodesRequest extends AdminMfaRotateRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->is_admin && $user->admin_mfa_confirmed_at !== null;
    }
}
