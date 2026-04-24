<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\LegalizacaoEtapa;
use Illuminate\Foundation\Http\FormRequest;

class ListLegalizacaoEtapasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LegalizacaoEtapa::class) ?? false;
    }
}
