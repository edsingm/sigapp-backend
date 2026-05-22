<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Table('plan_role_permission_templates')]
#[Fillable(['plan_id', 'role_slug', 'permission_name', 'is_required', 'is_default'])]
class PlanRolePermissionTemplate extends Model
{
    use CentralConnection, HasFactory;

    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
            'is_required' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
