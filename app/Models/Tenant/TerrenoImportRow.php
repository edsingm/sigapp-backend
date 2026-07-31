<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\TerrenoImportRowStatus;
use Database\Factories\Tenant\TerrenoImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $terreno_import_id
 * @property int $row_number
 * @property array<string, mixed> $raw_data
 * @property array<string, mixed>|null $normalized_data
 * @property TerrenoImportRowStatus $status
 * @property array<string, list<string>>|null $errors
 * @property int|null $terreno_id
 */
#[Table('terreno_import_rows')]
#[Fillable(['terreno_import_id', 'row_number', 'raw_data', 'normalized_data', 'status', 'errors', 'terreno_id'])]
class TerrenoImportRow extends Model
{
    /** @use HasFactory<TerrenoImportRowFactory> */
    use HasFactory;

    protected $casts = [
        'row_number' => 'integer',
        'raw_data' => 'array',
        'normalized_data' => 'array',
        'status' => TerrenoImportRowStatus::class,
        'errors' => 'array',
    ];

    /** @return BelongsTo<TerrenoImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(TerrenoImport::class, 'terreno_import_id');
    }

    /** @return BelongsTo<Terreno, $this> */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }
}
