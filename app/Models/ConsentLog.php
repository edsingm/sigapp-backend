<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['consent_id', 'categories', 'version', 'ip_hash', 'user_agent', 'consented_at'])]
/**
 * @property int $id
 * @property string $consent_id
 * @property array<string, bool> $categories
 * @property string $version
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property Carbon $consented_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConsentLog extends Model
{
    use CentralConnection, HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'categories' => 'array',
        'consented_at' => 'datetime',
    ];
}
