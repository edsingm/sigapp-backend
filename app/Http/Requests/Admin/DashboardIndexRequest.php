<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Central\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DashboardIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', Tenant::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }
}
