<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('theme')->nullable();
            $table->string('timezone')->nullable();
            $table->string('density')->nullable();
            $table->string('dashboard_layout')->nullable();
            $table->json('favorites')->nullable();
            $table->json('recent')->nullable();
        });

        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('resource');
            $table->string('scope')->default('private');
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->json('sort')->nullable();
            $table->string('view_mode')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['owner_id', 'resource', 'scope']);
            $table->index(['owner_id', 'resource', 'is_default']);
        });

        Schema::create('saved_view_user', function (Blueprint $table): void {
            $table->foreignId('saved_view_id')->constrained('saved_views')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['saved_view_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_view_user');
        Schema::dropIfExists('saved_views');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['theme', 'timezone', 'density', 'dashboard_layout', 'favorites', 'recent']);
        });
    }
};
