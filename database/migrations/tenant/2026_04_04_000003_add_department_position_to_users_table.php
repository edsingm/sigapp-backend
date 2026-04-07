<?php

use App\Models\Tenant\Department;
use App\Models\Tenant\Position;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignIdFor(Department::class)
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete()
                ->after('locale');

            $table->foreignIdFor(Position::class)
                ->nullable()
                ->constrained('positions')
                ->nullOnDelete()
                ->after('department_id');

            $table->index('department_id');
            $table->index('position_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['position_id']);
            $table->dropColumn(['department_id', 'position_id']);
        });
    }
};
