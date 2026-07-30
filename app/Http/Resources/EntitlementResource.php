<?php

namespace App\Http\Resources;

use App\Enums\Common\EntitlementScope;
use App\Models\Central\Entitlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Entitlement */
class EntitlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pivotValue = null;
        if (isset($this->pivot) && array_key_exists('value', $this->pivot->getAttributes())) {
            $raw = $this->pivot->value;
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $pivotValue = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        }

        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'type' => $this->type->value,
            'scope' => ($this->scope ?? EntitlementScope::INTERNAL)->value,
            'default_value' => $this->default_value,
            // Presente quando o entitlement vem do relacionamento plan→entitlements (pivot).
            'value' => $this->when(isset($this->pivot), $pivotValue),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
