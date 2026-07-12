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
 * @property string $status
 * @property string|null $provider
 * @property string|null $model
 * @property float|null $confidence
 * @property array<string, mixed>|null $extracted_fields
 * @property array<int, string>|null $limitations
 * @property string|null $error_message
 * @property Carbon|null $completed_at
 */
#[Table('document_analyses')]
#[Fillable(['documento_id', 'requested_by', 'status', 'provider', 'model', 'confidence', 'extracted_fields', 'limitations', 'error_message', 'completed_at'])]
class DocumentAnalysis extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = ['confidence' => 'float', 'extracted_fields' => 'array', 'limitations' => 'array', 'completed_at' => 'datetime'];
}
