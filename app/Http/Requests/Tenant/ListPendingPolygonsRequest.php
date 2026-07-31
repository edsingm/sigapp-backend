<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class ListPendingPolygonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Terreno::class);
    }

    protected function prepareForValidation(): void
    {
        $parts = array_map('trim', explode(',', (string) $this->input('bbox', '')));
        if (count($parts) === 4) {
            $this->merge([
                'min_lng' => $parts[0],
                'min_lat' => $parts[1],
                'max_lng' => $parts[2],
                'max_lat' => $parts[3],
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bbox' => ['required', 'string'],
            'min_lng' => ['required', 'numeric', 'between:-180,180', 'lt:max_lng'],
            'min_lat' => ['required', 'numeric', 'between:-90,90', 'lt:max_lat'],
            'max_lng' => ['required', 'numeric', 'between:-180,180'],
            'max_lat' => ['required', 'numeric', 'between:-90,90'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
