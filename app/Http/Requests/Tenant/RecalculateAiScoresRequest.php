<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RecalculateAiScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Terreno::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
