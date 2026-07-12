<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

class UpdateMobileCaptureRequest extends MobileCaptureRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'client_id' => ['prohibited'],
            'base_version' => ['required', 'integer', 'min:1'],
        ]);
    }
}
