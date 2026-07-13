<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user()?->isAdmin()
            || (($task instanceof Task) && $this->user()?->can('viewAny', Terreno::class));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'mentions' => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
