<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'level',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'level'  => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Positions with a higher hierarchy (lower level value = higher in the organization).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Position>
     */
    public function scopeAboveLevel(\Illuminate\Database\Eloquent\Builder $query, int $level): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('level', '<', $level);
    }

    /**
     * Positions with hierarchy equal to or above the given level.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Position>
     */
    public function scopeAtOrAboveLevel(\Illuminate\Database\Eloquent\Builder $query, int $level): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('level', '<=', $level);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'position_id');
    }
}
