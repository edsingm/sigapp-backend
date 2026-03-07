<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_role_permission_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('role_slug', 120);
            $table->string('permission_name', 191);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'role_slug', 'permission_name'], 'plan_role_perm_templates_unique');
            $table->index(['plan_id', 'role_slug'], 'plan_role_perm_templates_plan_role_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_role_permission_templates');
    }
};
