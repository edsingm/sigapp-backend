<?php

namespace App\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiConversationRepository
{
    /**
     * Get recent conversations for a user.
     *
     * @return Collection<int, object>
     */
    public function getRecentConversations(int $userId, int $limit = 50): Collection
    {
        return DB::table('agent_conversations')
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'created_at', 'updated_at'])
            ->map(function (object $row): object {
                $row->created_at = $row->created_at ? Carbon::parse($row->created_at)->toISOString() : null;
                $row->updated_at = $row->updated_at ? Carbon::parse($row->updated_at)->toISOString() : null;

                return $row;
            });
    }

    public function conversationExists(string $conversationId, int|string|null $userId): bool
    {
        return DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get messages for a conversation.
     *
     * @return Collection<int, object>
     */
    public function getMessages(string $conversationId): Collection
    {
        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at'])
            ->map(function (object $row): object {
                $row->created_at = $row->created_at ? Carbon::parse($row->created_at)->toISOString() : null;

                return $row;
            });
    }
}
