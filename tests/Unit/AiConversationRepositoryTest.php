<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Repositories\AiConversationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiConversationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private AiConversationRepository $repository;

    private User $owner;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        $this->repository = new AiConversationRepository;
        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner-conversation@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->other = User::create([
            'name' => 'Other',
            'email' => 'other-conversation@test.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_agent_conversations_schema_uses_polymorphic_participant(): void
    {
        $this->assertTrue(Schema::hasColumns('agent_conversations', [
            'participant_type',
            'participant_id',
        ]));
        $this->assertFalse(Schema::hasColumn('agent_conversations', 'user_id'));

        $this->assertTrue(Schema::hasColumns('agent_conversation_messages', [
            'participant_type',
            'participant_id',
            'approval_state',
        ]));
        $this->assertFalse(Schema::hasColumn('agent_conversation_messages', 'user_id'));
    }

    public function test_lists_only_conversations_for_participant(): void
    {
        $ownerConversation = $this->insertConversation($this->owner, 'Minha conversa');
        $this->insertConversation($this->other, 'Conversa alheia');

        $rows = $this->repository->getRecentConversations($this->owner->id);

        $this->assertCount(1, $rows);
        $this->assertSame([$ownerConversation], $rows->pluck('id')->all());
        $this->assertSame(['Minha conversa'], $rows->pluck('title')->all());
    }

    public function test_conversation_exists_enforces_ownership(): void
    {
        $conversationId = $this->insertConversation($this->owner, 'Privada');

        $this->assertTrue($this->repository->conversationExists($conversationId, $this->owner->id));
        $this->assertFalse($this->repository->conversationExists($conversationId, $this->other->id));
        $this->assertFalse($this->repository->conversationExists($conversationId, null));
    }

    public function test_get_messages_returns_user_and_assistant_roles(): void
    {
        $conversationId = $this->insertConversation($this->owner, 'Com mensagens');
        $this->insertMessage($conversationId, $this->owner, 'user', 'Olá');
        $this->insertMessage($conversationId, $this->owner, 'assistant', 'Oi');
        $this->insertMessage($conversationId, $this->owner, 'tool', 'ignorar');

        $messages = $this->repository->getMessages($conversationId);

        $this->assertCount(2, $messages);
        $this->assertSame(['user', 'assistant'], $messages->pluck('role')->all());
    }

    private function insertConversation(User $user, string $title): string
    {
        $id = (string) Str::uuid();

        DB::table('agent_conversations')->insert([
            'id' => $id,
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->id,
            'title' => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertMessage(string $conversationId, User $user, string $role, string $content): void
    {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->id,
            'agent' => 'App\\Services\\Ai\\Agents\\SIG_IA',
            'role' => $role,
            'content' => $content,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'approval_state' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
