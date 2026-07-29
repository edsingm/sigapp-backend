<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\User;
use App\Services\Acl\PermissionNameResolver;
use Illuminate\Foundation\Http\FormRequest;

class ViewAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && app(PermissionNameResolver::class)->userCan($user, 'ai.viewer');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
