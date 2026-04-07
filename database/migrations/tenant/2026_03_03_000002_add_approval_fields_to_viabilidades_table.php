<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            if (! Schema::hasColumn('viabilidades', 'approval_status')) {
                $table->string('approval_status')->default('pendente')->after('status');
            }

            if (! Schema::hasColumn('viabilidades', 'approval_requested_at')) {
                $table->timestamp('approval_requested_at')->nullable()->after('approval_status');
            }

            if (! Schema::hasColumn('viabilidades', 'approval_decided_at')) {
                $table->timestamp('approval_decided_at')->nullable()->after('approval_requested_at');
            }

            if (! Schema::hasColumn('viabilidades', 'approval_decided_by')) {
                $table->foreignId('approval_decided_by')->nullable()->after('approval_decided_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('viabilidades', 'approval_notes')) {
                $table->text('approval_notes')->nullable()->after('approval_decided_by');
            }
        });

        DB::table('viabilidades')
            ->whereNull('deleted_at')
            ->update([
                'approval_status' => DB::raw("CASE WHEN status = 'ativo' THEN 'aprovada' ELSE 'pendente' END"),
                'approval_decided_at' => DB::raw("CASE WHEN status = 'ativo' THEN updated_at ELSE NULL END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            if (Schema::hasColumn('viabilidades', 'approval_notes')) {
                $table->dropColumn('approval_notes');
            }

            if (Schema::hasColumn('viabilidades', 'approval_decided_by')) {
                $table->dropConstrainedForeignId('approval_decided_by');
            }

            if (Schema::hasColumn('viabilidades', 'approval_decided_at')) {
                $table->dropColumn('approval_decided_at');
            }

            if (Schema::hasColumn('viabilidades', 'approval_requested_at')) {
                $table->dropColumn('approval_requested_at');
            }

            if (Schema::hasColumn('viabilidades', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
};
