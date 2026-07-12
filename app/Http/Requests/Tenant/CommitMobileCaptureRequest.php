<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class CommitMobileCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Terreno::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'base_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
