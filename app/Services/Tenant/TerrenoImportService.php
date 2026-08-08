<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\TerrenoImportRowStatus;
use App\Enums\TerrenoImportStatus;
use App\Exceptions\TerrenoImportException;
use App\Jobs\CommitTerrenoImportJob;
use App\Jobs\ValidateTerrenoImportJob;
use App\Models\Tenant\TerrenoImport;
use App\Models\Tenant\User;
use App\Repositories\Tenant\TerrenoImportRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class TerrenoImportService
{
    public function __construct(
        private readonly TerrenoImportRepository $repository,
        private readonly StorageQuotaService $storageQuota,
        private readonly TerrenoSpreadsheetService $spreadsheets,
    ) {}

    public function create(User $user, string $idempotencyKey, UploadedFile $file): TerrenoImport
    {
        $existing = $this->repository->findByIdempotency($user, $idempotencyKey);
        if ($existing instanceof TerrenoImport) {
            return $existing;
        }

        $diskName = 's3';
        $fileName = $file->getClientOriginalName();
        $path = 'imports/terrenos/'.Str::uuid().'.xlsx';
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || ! Storage::disk($diskName)->put($path, $contents)) {
            throw new TerrenoImportException(
                'TERRAIN_IMPORT_UPLOAD_FAILED',
                'Não foi possível armazenar a planilha. Tente novamente.',
                422,
            );
        }

        $import = $this->storageQuota->commitFile(
            $diskName,
            $path,
            fn (int $size): TerrenoImport => $this->repository->createOrFind(
                $user,
                $idempotencyKey,
                [
                    'status' => TerrenoImportStatus::QUEUED,
                    'progress' => 0,
                    'storage_disk' => $diskName,
                    'storage_path' => $path,
                    'file_name' => $fileName,
                    'mime_type' => $file->getMimeType(),
                    'size' => $size,
                    'checksum' => hash('sha256', $contents),
                    'requested_at' => now(),
                    'expires_at' => now()->addDays(30),
                ],
            ),
        );

        if (! $import->wasRecentlyCreated) {
            Storage::disk($diskName)->delete($path);

            return $import;
        }

        ValidateTerrenoImportJob::dispatch($import->id);

        return $import;
    }

    public function find(User $user, int $id): TerrenoImport
    {
        return $this->repository->findForUser($user, $id);
    }

    public function confirm(User $user, int $id): TerrenoImport
    {
        $existing = $this->repository->findForUser($user, $id);
        if (in_array($existing->status, [TerrenoImportStatus::IMPORTING, TerrenoImportStatus::COMPLETED], true)) {
            return $existing;
        }
        if ($existing->status === TerrenoImportStatus::INVALID) {
            throw new TerrenoImportException(
                'TERRAIN_IMPORT_HAS_ERRORS',
                'A importação possui linhas inválidas.',
                422,
                ['invalid_rows' => $existing->invalid_rows],
            );
        }

        $import = $this->repository->confirmForImport($user, $id);
        if (! $import instanceof TerrenoImport) {
            throw new TerrenoImportException(
                'TERRAIN_IMPORT_NOT_READY',
                'A importação ainda não está pronta para confirmação.',
            );
        }

        CommitTerrenoImportJob::dispatch($import->id);

        return $import;
    }

    public function rows(User $user, int $id, ?TerrenoImportRowStatus $status, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateRows($user, $id, $status, $perPage);
    }

    public function templatePath(): string
    {
        return $this->spreadsheets->createTemplate();
    }

    public function errorReportPath(User $user, int $id): string
    {
        $import = $this->repository->findForUser($user, $id);
        if ($import->invalid_rows < 1) {
            throw new TerrenoImportException(
                'TERRAIN_IMPORT_HAS_NO_ERRORS',
                'A importação não possui erros para exportar.',
                404,
            );
        }

        $rows = $this->repository->invalidRowsForReport($user, $id);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Erros');
        $sheet->fromArray(['linha', 'dados', 'erros'], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $outputRow = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueExplicit("A{$outputRow}", (string) $row->row_number, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$outputRow}", json_encode($row->raw_data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$outputRow}", json_encode($row->errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), DataType::TYPE_STRING);
            $outputRow++;
        }
        $path = tempnam(sys_get_temp_dir(), 'sigapp-terrenos-erros-');
        if ($path === false) {
            throw new RuntimeException('Não foi possível criar o relatório de erros.');
        }
        $xlsxPath = $path.'.xlsx';
        @unlink($path);
        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return $xlsxPath;
    }
}
