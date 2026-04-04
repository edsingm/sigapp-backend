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
        if (!Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->id()->autoIncrement();
                $table->string('slug')->unique();
                $table->string('icon')->nullable();
                $table->json('resources')->nullable();
                $table->text('description')->nullable();
                $table->tinyInteger('order')->nullable()->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('modules')) {
            Schema::dropIfExists('modules');
        }
    }
};
