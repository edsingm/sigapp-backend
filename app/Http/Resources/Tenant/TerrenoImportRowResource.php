<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\TerrenoImportRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TerrenoImportRow */
class TerrenoImportRowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TerrenoImportRow $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'row_number' => $row->row_number,
            'status' => $row->status->value,
            'raw_data' => $row->raw_data,
            'normalized_data' => $row->normalized_data,
            'errors' => $row->errors,
            'terreno_id' => $row->terreno_id,
        ];
    }
}
