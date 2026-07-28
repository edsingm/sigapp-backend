<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property int $announcement_id
 * @property string $tenant_id
 * @property int $user_id
 */
#[Fillable(['announcement_id', 'tenant_id', 'user_id'])]
class PlatformAnnouncementDismissal extends Model
{
    use CentralConnection;

    /**
     * @return BelongsTo<PlatformAnnouncement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(PlatformAnnouncement::class, 'announcement_id');
    }
}
