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
     * O método "booted" do modelo.
     */
    protected static function booted(): void
    {        static::saved(function (TerrenoProduto $item) {
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
     * Obtém o terreno proprietário do produto do terreno.
     */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }
    /**
     * Obtém o produto proprietário do produto do terreno.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
    /**
     * Obtém o usuário que criou o produto do terreno.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    /**
     * Obtém o usuário que atualizou o produto da área.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    /**
     * Obtém os produtos da área por ID do produto.
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
