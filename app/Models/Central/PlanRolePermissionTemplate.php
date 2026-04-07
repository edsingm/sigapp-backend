<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PlanRolePermissionTemplate extends Model
{
    use CentralConnection, HasFactory;

    protected $table = 'plan_role_permission_templates';

    protected $fillable = [
        'plan_id',
        'role_slug',
        'permission_name',
        'is_required',
        'is_default',
    ];

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
