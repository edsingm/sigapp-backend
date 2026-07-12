<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\ShortlistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('shortlists')]
#[Fillable(['owner_id', 'name', 'description', 'scope', 'is_default'])]
/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string|null $description
 * @property string $scope
 * @property bool $is_default
 * @property-read User|null $owner
 * @property-read Collection<int, ShortlistItem> $items
 */
class Shortlist extends Model
{
    /** @use HasFactory<ShortlistFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<ShortlistItem, $this>
     */
    public function items(): HasMany
    {
        $relation = $this->hasMany(ShortlistItem::class);
        $relation->orderBy('position');

        return $relation;
    }
}
