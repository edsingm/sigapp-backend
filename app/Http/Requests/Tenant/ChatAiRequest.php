<?php

namespace App\Http\Requests\Tenant;

class ChatAiRequest extends ViewAiRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
