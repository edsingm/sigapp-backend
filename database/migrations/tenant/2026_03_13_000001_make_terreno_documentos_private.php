<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terreno_documentos', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('descricao');
        });

        DB::table('terreno_documentos')
            ->select(['id', 'url'])
            ->orderBy('id')
            ->get()
            ->each(function (object $documento): void {
                $relativePath = $this->normalizeLegacyPath($documento->url);

                if ($relativePath !== null && Storage::disk('public')->exists($relativePath)) {
                    if (!Storage::disk('local')->exists($relativePath)) {
                        Storage::disk('local')->put($relativePath, Storage::disk('public')->get($relativePath));
                    }

                    Storage::disk('public')->delete($relativePath);
                }

                DB::table('terreno_documentos')
                    ->where('id', $documento->id)
                    ->update(['file_path' => $relativePath]);
            });

        Schema::table('terreno_documentos', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }

    public function down(): void
    {
        Schema::table('terreno_documentos', function (Blueprint $table) {
            $table->string('url')->nullable()->after('descricao');
        });

        DB::table('terreno_documentos')
            ->select(['id', 'file_path'])
            ->orderBy('id')
            ->get()
            ->each(function (object $documento): void {
                $legacyUrl = null;

                if (filled($documento->file_path)) {
                    $legacyUrl = Storage::disk('public')->url((string) $documento->file_path);

                    if (Storage::disk('local')->exists((string) $documento->file_path)) {
                        if (!Storage::disk('public')->exists((string) $documento->file_path)) {
                            Storage::disk('public')->put(
                                (string) $documento->file_path,
                                Storage::disk('local')->get((string) $documento->file_path)
                            );
                        }

                        Storage::disk('local')->delete((string) $documento->file_path);
                    }
                }

                DB::table('terreno_documentos')
                    ->where('id', $documento->id)
                    ->update(['url' => $legacyUrl]);
            });

        Schema::table('terreno_documentos', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });
    }

    private function normalizeLegacyPath(?string $url): ?string
    {
        if (!is_string($url) || trim($url) === '') {
            return null;
        }

        $path = trim($url);
        $parsedPath = parse_url($path, PHP_URL_PATH);

        if (is_string($parsedPath) && $parsedPath !== '') {
            $path = $parsedPath;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? rawurldecode($path) : null;
    }
};
