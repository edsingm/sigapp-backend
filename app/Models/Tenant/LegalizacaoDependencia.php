<?php

namespace App\Models\Tenant;

use App\Traits\HasDashboardCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('legalizacao_dependencias')]
#[Fillable(['legalizacao_id', 'etapa_origem_id', 'etapa_destino_id', 'tipo'])]
class LegalizacaoDependencia extends Model
{
    use HasDashboardCache, HasFactory;

    protected $casts = [
        'tipo' => 'string',
    ];

    /**
     * @return list<string>
     */
    protected function tenantCacheModules(): array
    {
        return ['legalizacoes', 'legalizacao_dependencias'];
    }

    public function legalizacao(): BelongsTo
    {
        return $this->belongsTo(Legalizacao::class);
    }

    public function etapaOrigem(): BelongsTo
    {
        return $this->belongsTo(LegalizacaoEtapa::class, 'etapa_origem_id');
    }

    public function etapaDestino(): BelongsTo
    {
        return $this->belongsTo(LegalizacaoEtapa::class, 'etapa_destino_id');
    }
}
