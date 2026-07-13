<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $documento_id
 * @property int $version
 * @property string $file_path
 * @property string $disk
 * @property string|null $mime_type
 * @property int|null $size
 * @property string $checksum
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
#[Table('document_versions')]
#[Fillable(['documento_id', 'version', 'file_path', 'disk', 'mime_type', 'size', 'checksum', 'created_by', 'metadata'])]
class DocumentVersion extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = ['version' => 'integer', 'size' => 'integer', 'metadata' => 'array'];
}
