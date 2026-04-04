<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('level')->default(1)->comment('Hierarchy: lower value = higher in the organization');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('level');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
