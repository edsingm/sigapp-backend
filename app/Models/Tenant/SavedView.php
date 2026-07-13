<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\SavedViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string|null $description
 * @property string $resource
 * @property string $scope
 * @property array<string, mixed>|null $filters
 * @property array<int, string>|null $columns
 * @property array<int, mixed>|null $sort
 * @property string|null $view_mode
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $owner
 * @property-read Collection<int, User> $sharedWith
 */
#[Table('saved_views')]
#[Fillable(['owner_id', 'name', 'description', 'resource', 'scope', 'filters', 'columns', 'sort', 'view_mode', 'is_default'])]
class SavedView extends Model
{
    /** @use HasFactory<SavedViewFactory> */
    use HasFactory;

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'sort' => 'array',
        'is_default' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_view_user');
    }
}
