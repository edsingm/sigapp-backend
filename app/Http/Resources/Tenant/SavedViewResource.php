<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\SavedView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedViewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var SavedView $view */
        $view = $this->resource;

        return [
            'id' => $view->id,
            'name' => $view->name,
            'description' => $view->description,
            'resource' => $view->resource,
            'scope' => $view->scope,
            'is_default' => $view->is_default,
            'filters' => $view->filters ?? [],
            'columns' => $view->columns ?? [],
            'sort' => $view->sort ?? [],
            'view_mode' => $view->view_mode,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $view->owner?->id,
                'name' => $view->owner?->name,
                'email' => $view->owner?->email,
                'avatar_url' => null,
            ]),
            'shared_with' => $this->whenLoaded('sharedWith', fn () => $view->sharedWith->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => null,
            ])->values()),
            'created_at' => $view->created_at?->toIso8601String(),
            'updated_at' => $view->updated_at?->toIso8601String(),
        ];
    }
}
