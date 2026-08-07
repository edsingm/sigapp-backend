<?php

declare(strict_types=1);

use App\Models\Tenant\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade agent conversation tables for laravel/ai 0.10:
 * - polymorphic participant (user_id → participant_id + participant_type)
 * - approval_state column for HITL tool approval
 *
 * Do not edit the original create migration; environments already ran it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conversationsTable = $this->conversationsTable();
        $messagesTable = $this->messagesTable();

        if (! Schema::hasTable($conversationsTable) || ! Schema::hasTable($messagesTable)) {
            return;
        }

        if (Schema::hasColumn($conversationsTable, 'user_id')) {
            Schema::table($conversationsTable, function (Blueprint $table) use ($conversationsTable): void {
                $this->dropIndexIfExists($conversationsTable, $table, ['user_id', 'updated_at']);
                $table->renameColumn('user_id', 'participant_id');
            });
        }

        if (! Schema::hasColumn($conversationsTable, 'participant_type')) {
            Schema::table($conversationsTable, function (Blueprint $table): void {
                $table->string('participant_type')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn($messagesTable, 'user_id')) {
            Schema::table($messagesTable, function (Blueprint $table) use ($messagesTable): void {
                $this->dropIndexIfExists($messagesTable, $table, 'conversation_index');
                $this->dropIndexIfExists($messagesTable, $table, ['user_id']);
                $table->renameColumn('user_id', 'participant_id');
            });
        }

        if (! Schema::hasColumn($messagesTable, 'participant_type')) {
            Schema::table($messagesTable, function (Blueprint $table): void {
                $table->string('participant_type')->nullable()->after('conversation_id');
            });
        }

        $participantType = (new User)->getMorphClass();

        DB::table($conversationsTable)
            ->whereNotNull('participant_id')
            ->whereNull('participant_type')
            ->update(['participant_type' => $participantType]);

        DB::table($messagesTable)
            ->whereNotNull('participant_id')
            ->whereNull('participant_type')
            ->update(['participant_type' => $participantType]);

        Schema::table($conversationsTable, function (Blueprint $table) use ($conversationsTable): void {
            if (! $this->indexExists($conversationsTable, 'participant_updated_at_index')) {
                $table->index(
                    ['participant_type', 'participant_id', 'updated_at'],
                    'participant_updated_at_index',
                );
            }
        });

        Schema::table($messagesTable, function (Blueprint $table) use ($messagesTable): void {
            if (! $this->indexExists($messagesTable, 'conversation_index')) {
                $table->index(
                    ['conversation_id', 'participant_type', 'participant_id', 'updated_at'],
                    'conversation_index',
                );
            }

            if (! $this->indexExists($messagesTable, 'participant_index')) {
                $table->index(
                    ['participant_type', 'participant_id'],
                    'participant_index',
                );
            }
        });

        if (! Schema::hasColumn($messagesTable, 'approval_state')) {
            Schema::table($messagesTable, function (Blueprint $table): void {
                $table->text('approval_state')->nullable()->after('meta');
            });
        }
    }

    public function down(): void
    {
        $conversationsTable = $this->conversationsTable();
        $messagesTable = $this->messagesTable();

        if (! Schema::hasTable($conversationsTable) || ! Schema::hasTable($messagesTable)) {
            return;
        }

        if (Schema::hasColumn($messagesTable, 'approval_state')) {
            Schema::table($messagesTable, function (Blueprint $table): void {
                $table->dropColumn('approval_state');
            });
        }

        Schema::table($messagesTable, function (Blueprint $table) use ($messagesTable): void {
            $this->dropIndexIfExists($messagesTable, $table, 'conversation_index');
            $this->dropIndexIfExists($messagesTable, $table, 'participant_index');
        });

        Schema::table($conversationsTable, function (Blueprint $table) use ($conversationsTable): void {
            $this->dropIndexIfExists($conversationsTable, $table, 'participant_updated_at_index');
        });

        if (Schema::hasColumn($messagesTable, 'participant_type')) {
            Schema::table($messagesTable, function (Blueprint $table): void {
                $table->dropColumn('participant_type');
            });
        }

        if (Schema::hasColumn($conversationsTable, 'participant_type')) {
            Schema::table($conversationsTable, function (Blueprint $table): void {
                $table->dropColumn('participant_type');
            });
        }

        if (Schema::hasColumn($messagesTable, 'participant_id') && ! Schema::hasColumn($messagesTable, 'user_id')) {
            Schema::table($messagesTable, function (Blueprint $table): void {
                $table->renameColumn('participant_id', 'user_id');
            });
        }

        if (Schema::hasColumn($conversationsTable, 'participant_id') && ! Schema::hasColumn($conversationsTable, 'user_id')) {
            Schema::table($conversationsTable, function (Blueprint $table): void {
                $table->renameColumn('participant_id', 'user_id');
            });
        }

        Schema::table($conversationsTable, function (Blueprint $table) use ($conversationsTable): void {
            if (! $this->indexExists($conversationsTable, $this->defaultIndexName($conversationsTable, ['user_id', 'updated_at']))) {
                $table->index(['user_id', 'updated_at']);
            }
        });

        Schema::table($messagesTable, function (Blueprint $table) use ($messagesTable): void {
            if (! $this->indexExists($messagesTable, 'conversation_index')) {
                $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
            }

            if (! $this->indexExists($messagesTable, $this->defaultIndexName($messagesTable, ['user_id']))) {
                $table->index(['user_id']);
            }
        });
    }

    private function conversationsTable(): string
    {
        return (string) config('ai.conversations.tables.conversations', 'agent_conversations');
    }

    private function messagesTable(): string
    {
        return (string) config('ai.conversations.tables.messages', 'agent_conversation_messages');
    }

    /**
     * @param  string|list<string>  $index
     */
    private function dropIndexIfExists(string $tableName, Blueprint $table, string|array $index): void
    {
        if (is_string($index)) {
            if ($this->indexExists($tableName, $index)) {
                $table->dropIndex($index);
            }

            return;
        }

        $defaultName = $this->defaultIndexName($tableName, $index);
        if ($this->indexExists($tableName, $defaultName)) {
            $table->dropIndex($index);

            return;
        }

        // Fallback: some drivers auto-name indexes differently.
        foreach (Schema::getIndexes($tableName) as $existing) {
            $columns = $existing['columns'] ?? [];
            if ($columns === $index) {
                $name = $existing['name'] ?? null;
                if (is_string($name) && $name !== '') {
                    $table->dropIndex($name);
                }

                return;
            }
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        foreach (Schema::getIndexes($tableName) as $existing) {
            if (($existing['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function defaultIndexName(string $tableName, array $columns): string
    {
        return $tableName.'_'.implode('_', $columns).'_index';
    }
};
