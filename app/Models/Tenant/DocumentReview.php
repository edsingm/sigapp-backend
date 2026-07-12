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
 * @property int|null $reviewer_id
 * @property string $status
 * @property Carbon|null $valid_until
 * @property string|null $notes
 * @property Carbon|null $reviewed_at
 */
#[Table('document_reviews')]
#[Fillable(['documento_id', 'reviewer_id', 'status', 'valid_until', 'notes', 'reviewed_at'])]
class DocumentReview extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = ['valid_until' => 'date', 'reviewed_at' => 'datetime'];
}
