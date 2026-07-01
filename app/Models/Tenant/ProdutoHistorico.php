<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $produto_id
 * @property string $action
 * @property array<string, mixed>|null $before_values
 * @property array<string, mixed>|null $after_values
 * @property int|null $changed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $changedBy
 */
#[Fillable(['produto_id', 'action', 'before_values', 'after_values', 'changed_by'])]
class ProdutoHistorico extends Model
{
    protected $table = 'produto_historicos';

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
    ];

    /**
     * @return BelongsTo<Produto, $this>
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
