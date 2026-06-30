<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'position_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['position_id']);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['position_id']);
                $table->dropColumn('position_id');
            });
        }

        Schema::dropIfExists('positions');
    }

    public function down(): void
    {
        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('level')->default(1);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique('name');
                $table->index(['active', 'level']);
            });
        }

        if (! Schema::hasColumn('users', 'position_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('position_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('positions')
                    ->nullOnDelete();

                $table->index('position_id');
            });
        }
    }
};
