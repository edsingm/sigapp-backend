<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CorretorExterno;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

class DestroyCorretorExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');

        if (! $id) {
            return false;
        }

        try {
            $corretor = CorretorExterno::findOrFail($id);
        } catch (ModelNotFoundException) {
            return false;
        }

        return $this->user()?->can('delete', $corretor) ?? false;
    }
}
