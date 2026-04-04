<?php

namespace App\Models\Central;

use App\Enums\Common\EntitlementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property EntitlementType $type
 * @property mixed $default_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Entitlement extends Model
{
    use HasFactory, CentralConnection;

    protected $table = 'entitlements';

    protected $fillable = [
        'key',
        'label',
        'description',
        'type',
        'default_value',
    ];

    protected function casts(): array
    {
        return [
            'type'          => EntitlementType::class,
            'default_value' => 'json',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_entitlements')
            ->withPivot('value')
            ->withTimestamps();
    }
}
