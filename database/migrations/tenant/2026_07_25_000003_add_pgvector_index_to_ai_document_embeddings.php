<?php

declare(strict_types=1);

use App\Support\Database\PgVector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();

        PgVector::install($connection);
        PgVector::createEmbeddingIndex($connection);
    }

    public function down(): void
    {
        PgVector::dropEmbeddingIndex(Schema::getConnection());
    }
};
