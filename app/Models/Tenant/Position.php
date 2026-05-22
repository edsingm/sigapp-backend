<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'level', 'active'])]
class Position extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Positions with a higher hierarchy (lower level value = higher in the organization).
     *
     * @return Builder<Position>
     */
    public function scopeAboveLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', '<', $level);
    }

    /**
     * Positions with hierarchy equal to or above the given level.
     *
     * @return Builder<Position>
     */
    public function scopeAtOrAboveLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', '<=', $level);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'position_id');
    }
}
