<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasDashboardCache;

class TerrenoProduto extends Model
{
    use HasFactory, SoftDeletes, HasDashboardCache;

    protected $table = 'terreno_produtos';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (TerrenoProduto $item) {
            $item->clearTenantCache('terreno_produtos');
        });

        static::deleted(function (TerrenoProduto $item) {
            $item->clearTenantCache('terreno_produtos');
        });

        static::restored(function (TerrenoProduto $item) {
            $item->clearTenantCache('terreno_produtos');
        });
    }
    protected $fillable = [
        'terreno_id',
        'produto_id',
        'unidades',
        'valor',
        'permuta',
        'pgto_por_lote',
        'observacoes',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the terreno that owns the terreno produto.
     */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }
    /**
     * Get the product that owns the terreno produto.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
    /**
     * Get the user who created the terreno produto.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    /**
     * Get the user who updated the area produto.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    /**
     * Get the area produtos by produto id.
     */
    public function scopeProdutos($query, $produto_id)
    {
        return $query->where('produto_id', $produto_id);
    }
    public function scopeTerrenos($query, $terreno_id)
    {
        return $query->where('terreno_id', $terreno_id);
    }
    public function scopeUnidades($query, $unidades)
    {
        return $query->where('unidades', $unidades);
    }
    public function scopePermuta($query, $permuta)
    {
        return $query->where('permuta', $permuta);
    }
    public function scopePgtoPorLote($query, $pgto_por_lote)
    {
        return $query->where('pgto_por_lote', $pgto_por_lote);
    }
    public function scopeObservacoes($query, $observacoes)
    {
        return $query->where('observacoes', 'like', '%' . $observacoes . '%');
    }
    public function scopeCreatedBy($query, $created_by)
    {
        return $query->where('created_by', $created_by);
    }
    public function scopeUpdatedBy($query, $updated_by)
    {
        return $query->where('updated_by', $updated_by);
    }
    public function scopeValor($query, $valor)
    {
        return $query->where('valor', $valor);
    }
}
