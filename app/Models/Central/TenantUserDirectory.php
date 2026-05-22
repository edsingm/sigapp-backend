<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Table('tenant_user_directories')]
#[Fillable(['tenant_id', 'tenant_user_id', 'email_normalized', 'user_name', 'active'])]
class TenantUserDirectory extends Model
{
    use CentralConnection;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
