<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $columns = [];

            foreach (['entitlements', 'max_users', 'max_storage_gb', 'max_terrenos'] as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'max_users')) {
                $table->integer('max_users')->default(5)->after('trial_days');
            }

            if (! Schema::hasColumn('plans', 'max_storage_gb')) {
                $table->integer('max_storage_gb')->default(5)->after('max_users');
            }

            if (! Schema::hasColumn('plans', 'max_terrenos')) {
                $table->integer('max_terrenos')->default(100)->after('max_storage_gb');
            }

            if (! Schema::hasColumn('plans', 'entitlements')) {
                $table->json('entitlements')->nullable()->after('max_terrenos');
            }
        });
    }
};
