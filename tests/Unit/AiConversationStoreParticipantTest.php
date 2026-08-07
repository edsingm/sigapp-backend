<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Tests\TestCase;

class AiConversationStoreParticipantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
    }

    public function test_store_conversation_persists_polymorphic_participant(): void
    {
        $user = User::create([
            'name' => 'Store User',
            'email' => 'store-conversation@test.com',
            'password' => Hash::make('password123'),
        ]);

        $store = resolve(ConversationStore::class);
        $conversationId = $store->storeConversation(
            Conversation::participantType($user),
            Conversation::participantKey($user),
            'Título da conversa',
        );

        $this->assertNotEmpty($conversationId);

        $row = DB::table('agent_conversations')->where('id', $conversationId)->first();

        $this->assertNotNull($row);
        $this->assertSame($user->getMorphClass(), $row->participant_type);
        $this->assertSame($user->id, (int) $row->participant_id);
        $this->assertSame('Título da conversa', $row->title);
    }
}
