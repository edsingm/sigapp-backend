<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\TerrenoPolygonStatus;
use Carbon\Carbon;
use Database\Factories\Tenant\TerrenoPendingPolygonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $terreno_polygon_import_id
 * @property int $terreno_polygon_import_file_id
 * @property string|null $source_entry
 * @property string|null $placemark_name
 * @property int $geometry_index
 * @property list<array{lat: float, lng: float}> $polygon_coords
 * @property string $geometry_hash
 * @property float $min_lat
 * @property float $max_lat
 * @property float $min_lng
 * @property float $max_lng
 * @property TerrenoPolygonStatus $status
 * @property int|null $terreno_id
 * @property int|null $linked_by
 * @property Carbon|null $linked_at
 * @property-read TerrenoPolygonImportFile $file
 */
#[Table('terreno_pending_polygons')]
#[Fillable(['terreno_polygon_import_id', 'terreno_polygon_import_file_id', 'source_entry', 'placemark_name', 'geometry_index', 'polygon_coords', 'geometry_hash', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'status', 'terreno_id', 'linked_by', 'linked_at'])]
class TerrenoPendingPolygon extends Model
{
    /** @use HasFactory<TerrenoPendingPolygonFactory> */
    use HasFactory;

    protected $casts = [
        'geometry_index' => 'integer',
        'polygon_coords' => 'array',
        'min_lat' => 'float',
        'max_lat' => 'float',
        'min_lng' => 'float',
        'max_lng' => 'float',
        'status' => TerrenoPolygonStatus::class,
        'linked_at' => 'datetime',
    ];

    /** @return BelongsTo<TerrenoPolygonImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(TerrenoPolygonImport::class, 'terreno_polygon_import_id');
    }

    /** @return BelongsTo<TerrenoPolygonImportFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(TerrenoPolygonImportFile::class, 'terreno_polygon_import_file_id');
    }

    /** @return BelongsTo<Terreno, $this> */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    /** @return BelongsTo<User, $this> */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
