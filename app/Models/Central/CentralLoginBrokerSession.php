<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CentralLoginBrokerSession extends Model
{
    use CentralConnection;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'device_name',
        'ip_address',
        'user_agent',
        'tenant_options',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_options' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return ! $this->expires_at || $this->expires_at->isPast();
    }

    public function isCompleted(): bool
    {
        return (bool) $this->completed_at;
    }
}
