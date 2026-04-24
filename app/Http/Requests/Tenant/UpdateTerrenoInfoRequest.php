<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\TerrenoInfos;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTerrenoInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $infoId = $this->route('infoId');
        $info = $infoId instanceof TerrenoInfos ? $infoId : TerrenoInfos::find($infoId);

        return $user !== null
            && $info instanceof TerrenoInfos
            && $info->terreno !== null
            && $user->can('update', $info->terreno);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string'],
        ];
    }
}
