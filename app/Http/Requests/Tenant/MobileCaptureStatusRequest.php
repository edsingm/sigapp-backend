<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class MobileCaptureStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Terreno::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
