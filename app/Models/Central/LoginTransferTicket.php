<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class LoginTransferTicket extends Model
{
    use CentralConnection;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ticket_hash',
        'tenant_id',
        'tenant_user_id',
        'email',
        'device_name',
        'ip_address',
        'user_agent',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function isExpired(): bool
    {
        return !$this->expires_at || $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return (bool) $this->used_at;
    }
}
