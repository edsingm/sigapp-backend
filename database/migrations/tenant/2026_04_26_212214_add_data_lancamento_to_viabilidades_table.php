<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->date('data_lancamento')->nullable()->after('prazo_incorporacao')
                ->comment('Data base de lançamento do projeto');
        });
    }

    public function down(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn('data_lancamento');
        });
    }
};
