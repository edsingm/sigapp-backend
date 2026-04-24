<?php

namespace App\Repositories;

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
            ->get(['id', 'title', 'created_at', 'updated_at']);
    }

    public function conversationExists(int $conversationId, int|string|null $userId): bool
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
    public function getMessages(int $conversationId): Collection
    {
        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at']);
    }
}
