<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string $type
 * @property string $channel
 * @property string $segment
 * @property string|null $segment_value
 * @property string $status
 * @property int $recipients_count
 * @property Carbon|null $sent_at
 * @property int|null $created_by
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 */
#[Fillable([
    'title',
    'body',
    'type',
    'channel',
    'segment',
    'segment_value',
    'status',
    'recipients_count',
    'sent_at',
    'created_by',
    'meta',
])]
class PlatformAnnouncement extends Model
{
    use CentralConnection;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_BANNER = 'banner';

    public const CHANNEL_BOTH = 'both';

    public const SEGMENT_ALL = 'all';

    public const SEGMENT_PLAN = 'plan';

    public const SEGMENT_STATUS = 'status';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** Informativo genérico (padrão). */
    public const TYPE_INFO = 'info';

    /** Avisos de segurança / risco / credenciais. */
    public const TYPE_SECURITY = 'security';

    /** Promoções, descontos, campanhas comerciais. */
    public const TYPE_PROMO = 'promo';

    /** Manutenção, downtime, incidentes. */
    public const TYPE_MAINTENANCE = 'maintenance';

    /** Novidades de produto / changelog. */
    public const TYPE_UPDATE = 'update';

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_INFO,
            self::TYPE_SECURITY,
            self::TYPE_PROMO,
            self::TYPE_MAINTENANCE,
            self::TYPE_UPDATE,
        ];
    }

    /**
     * Prioridade de exibição no banner (menor = mais urgente).
     */
    public static function typePriority(string $type): int
    {
        return match ($type) {
            self::TYPE_SECURITY => 0,
            self::TYPE_MAINTENANCE => 1,
            self::TYPE_UPDATE => 2,
            self::TYPE_INFO => 3,
            self::TYPE_PROMO => 4,
            default => 5,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
