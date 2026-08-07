<?php

declare(strict_types=1);

namespace App\Models\Central;

use Carbon\Carbon;
use Database\Factories\Central\DemoRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $company
 * @property string|null $city
 * @property string|null $role
 * @property string|null $land_context
 * @property string $source
 * @property string|null $page
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'company',
    'city',
    'role',
    'land_context',
    'source',
    'page',
    'ip_hash',
    'user_agent',
])]
class DemoRequest extends Model
{
    /** @use HasFactory<DemoRequestFactory> */
    use CentralConnection, HasFactory;
}
