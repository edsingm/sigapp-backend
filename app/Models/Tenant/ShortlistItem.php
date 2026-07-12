<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\ShortlistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('shortlist_items')]
#[Fillable(['shortlist_id', 'terreno_id', 'position'])]
/**
 * @property int $id
 * @property int $shortlist_id
 * @property int $terreno_id
 * @property int $position
 * @property-read Shortlist|null $shortlist
 * @property-read Terreno|null $terreno
 */
class ShortlistItem extends Model
{
    /** @use HasFactory<ShortlistItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<Shortlist, $this>
     */
    public function shortlist(): BelongsTo
    {
        return $this->belongsTo(Shortlist::class);
    }

    /**
     * @return BelongsTo<Terreno, $this>
     */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }
}
