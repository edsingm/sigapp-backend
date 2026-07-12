<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class MobileCaptureAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Terreno::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,audio/mpeg,audio/wav,audio/mp4,audio/x-m4a'],
        ];
    }
}
