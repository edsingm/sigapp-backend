<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncPlanEntitlementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        return [
            'entitlements' => ['required', 'array'],
            'entitlements.*.entitlement_id' => ['required', 'integer', 'exists:entitlements,id'],
            'entitlements.*.value' => ['sometimes'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ids = collect($this->input('entitlements', []))
                    ->pluck('entitlement_id')
                    ->filter(static fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id));

                if ($ids->count() !== $ids->unique()->count()) {
                    $validator->errors()->add('entitlements', 'IDs de entitlement duplicados não são permitidos.');
                }
            },
        ];
    }
}
