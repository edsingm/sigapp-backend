<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $entity_type
 * @property int|null $entity_id
 * @property string $phase
 * @property string $document_type
 * @property string $label
 * @property bool $required
 * @property bool $active
 */
#[Table('document_requirements')]
#[Fillable(['entity_type', 'entity_id', 'phase', 'document_type', 'label', 'required', 'active', 'created_by'])]
class DocumentRequirement extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = ['required' => 'boolean', 'active' => 'boolean'];
}
