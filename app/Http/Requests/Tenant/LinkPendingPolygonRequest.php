<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class LinkPendingPolygonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $terrainId = $this->integer('terreno_id');
        $terrain = $terrainId > 0 ? Terreno::query()->find($terrainId) : null;

        return $terrain instanceof Terreno && (bool) $this->user()?->can('update', $terrain);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
        ];
    }
}
