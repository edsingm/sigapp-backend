<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope')->default('private');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['owner_id', 'scope']);
        });

        Schema::create('shortlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shortlist_id')->constrained('shortlists')->cascadeOnDelete();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['shortlist_id', 'terreno_id']);
            $table->index(['shortlist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortlist_items');
        Schema::dropIfExists('shortlists');
    }
};
