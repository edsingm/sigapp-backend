<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DashboardIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Mesmo padrão dos demais FormRequests de Admin central.
        // Gate::view(Tenant::class) apontava para TenantPolicy (contexto tenant) e negava sempre.
        return (bool) ($this->user()?->is_admin);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }
}
