<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ReadMobileNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
