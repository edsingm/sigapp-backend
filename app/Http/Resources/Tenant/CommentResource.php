<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Comment $comment */
        $comment = $this->resource;

        return [
            'id' => $comment->id,
            'comment' => $comment->comment,
            'user_id' => $comment->user_id,
            'user' => $this->whenLoaded('user', fn () => new UserResource($comment->user)),
            'created_at' => $comment->created_at?->toIso8601String(),
            'updated_at' => $comment->updated_at?->toIso8601String(),
        ];
    }
}
