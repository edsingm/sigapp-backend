<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['produto_id', 'action', 'before_values', 'after_values', 'changed_by'])]
class ProdutoHistorico extends Model
{
    protected $table = 'produto_historicos';

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
