<?php

namespace App\Services;

use App\Models\Central\Tenant;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class LimitEnforcementService
{
    protected Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Check if the tenant can create a new user.
     */
    public function canCreateUser(): bool
    {
        if ($this->tenant->max_users === -1) {
            return true;
        }

        // Count current users in the tenant context
        // We assume this runs in central context but we need to count tenant users.
        // If we are in tenant context, User::count() works.
        // If we are in central context, we might need to verify how to count.
        // Assuming we are calling this from within the tenant application:

        $currentUsers = User::count();

        return $currentUsers < $this->tenant->max_users;
    }

    /**
     * Check if the tenant can create a new terreno.
     */
    public function canCreateTerreno(): bool
    {
        if ($this->tenant->max_terrenos === -1) {
            return true;
        }

        $currentTerrenos = Terreno::count();

        return $currentTerrenos < $this->tenant->max_terrenos;
    }

    /**
     * Check if the tenant can upload a file of the given size (in KB).
     */
    public function canUploadFile(int $sizeInKb): bool
    {
        if ($this->tenant->max_storage_gb === -1) {
            return true;
        }

        // Sum size of all documents (assuming 'tamanho' is in bytes)
        // We divide by 1024 to get KB, then by 1024 to get MB, then by 1024 to get GB
        // Or simply: 1 GB = 1073741824 bytes

        $currentUsageBytes = Documento::sum('tamanho');

        $newSizeBytes = $sizeInKb * 1024;
        $totalBytes = $currentUsageBytes + $newSizeBytes;

        $maxBytes = $this->tenant->max_storage_gb * 1024 * 1024 * 1024;

        return $totalBytes <= $maxBytes;
    }
}
