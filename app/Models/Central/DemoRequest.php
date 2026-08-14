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
 * @property bool $accepted_privacy
 * @property Carbon|null $accepted_at
 * @property string|null $privacy_document_key
 * @property string|null $privacy_document_version
 * @property string|null $privacy_document_hash
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
    'accepted_privacy',
    'accepted_at',
    'privacy_document_key',
    'privacy_document_version',
    'privacy_document_hash',
])]
class DemoRequest extends Model
{
    /** @use HasFactory<DemoRequestFactory> */
    use CentralConnection, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_privacy' => 'boolean',
            'accepted_at' => 'datetime',
        ];
    }
}
