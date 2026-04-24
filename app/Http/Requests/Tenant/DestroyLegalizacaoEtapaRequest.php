<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\LegalizacaoEtapa;
use Illuminate\Foundation\Http\FormRequest;

class DestroyLegalizacaoEtapaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $etapa = $this->route('id');

        if (! $etapa) {
            return false;
        }

        return $this->user()?->can('delete', [LegalizacaoEtapa::class, $etapa]) ?? false;
    }
}
