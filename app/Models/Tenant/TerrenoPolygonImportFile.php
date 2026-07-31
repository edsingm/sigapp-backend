<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\TerrenoPolygonImportFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $terreno_polygon_import_id
 * @property string $file_name
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $checksum
 * @property string $status
 * @property string|null $error_message
 */
#[Table('terreno_polygon_import_files')]
#[Fillable(['terreno_polygon_import_id', 'file_name', 'storage_disk', 'storage_path', 'mime_type', 'size', 'checksum', 'status', 'error_message'])]
class TerrenoPolygonImportFile extends Model
{
    /** @use HasFactory<TerrenoPolygonImportFileFactory> */
    use HasFactory;

    protected $casts = ['size' => 'integer'];

    /** @return BelongsTo<TerrenoPolygonImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(TerrenoPolygonImport::class, 'terreno_polygon_import_id');
    }

    /** @return HasMany<TerrenoPendingPolygon, $this> */
    public function polygons(): HasMany
    {
        return $this->hasMany(TerrenoPendingPolygon::class);
    }
}
