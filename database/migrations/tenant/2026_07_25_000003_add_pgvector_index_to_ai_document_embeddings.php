<?php

declare(strict_types=1);

use App\Support\Database\PgVector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        PgVector::createEmbeddingIndex(Schema::getConnection());
    }

    public function down(): void
    {
        PgVector::dropEmbeddingIndex(Schema::getConnection());
    }
};
