<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mobile_capture_id
 * @property int|null $created_by
 * @property string $original_name
 * @property string $file_path
 * @property string $disk
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $checksum
 * @property string $status
 * @property Carbon|null $created_at
 */
#[Table('mobile_capture_attachments')]
#[Fillable(['mobile_capture_id', 'created_by', 'original_name', 'file_path', 'disk', 'mime_type', 'size', 'checksum', 'status'])]
class MobileCaptureAttachment extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    /** @return BelongsTo<MobileCapture, $this> */
    public function capture(): BelongsTo
    {
        return $this->belongsTo(MobileCapture::class, 'mobile_capture_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
