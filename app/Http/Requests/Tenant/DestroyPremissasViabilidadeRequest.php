<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Services\Acl\PermissionNameResolver;
use Illuminate\Foundation\Http\FormRequest;

class DestroyPremissasViabilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->isAdmin() || $user->hasPermissionTo(
            app(PermissionNameResolver::class)->forRequest('configurations', null, $this->method())
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
