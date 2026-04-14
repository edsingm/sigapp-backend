<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Regional extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * O método "booted" do modelo.
     */
    protected static function booted(): void
    {
        static::saved(function (Regional $model) {
            $tenantId = tenant('id') ?? 'central';
            Cache::tags(["tenant:{$tenantId}:regionais"])->flush();
            Cache::tags(["tenant:{$tenantId}:terrenos"])->flush();
        });

        static::deleted(function (Regional $model) {
            $tenantId = tenant('id') ?? 'central';
            Cache::tags(["tenant:{$tenantId}:regionais"])->flush();
            Cache::tags(["tenant:{$tenantId}:terrenos"])->flush();
        });

        static::restored(function (Regional $model) {
            $tenantId = tenant('id') ?? 'central';
            Cache::tags(["tenant:{$tenantId}:regionais"])->flush();
            Cache::tags(["tenant:{$tenantId}:terrenos"])->flush();
        });
    }

    /**
     * A tabela associada ao modelo.
     *
     * @var string
     */
    protected $table = 'regionais';

    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome',
        'estado',
        'cidade',
        'endereco',
        'numero',
        'telefone',
        'celular',
        'observacoes',
        'responsavel_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Os atributos que devem ser convertidos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Obtém o usuário responsável por esta regional.
     */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    /**
     * Obtém o usuário que criou esta regional.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Obtém o usuário que atualizou esta regional pela última vez.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Obtém o endereço completo.
     */
    public function getEnderecoCompletoAttribute(): string
    {
        return "{$this->endereco}, {$this->numero} - {$this->cidade}, {$this->estado}";
    }

    /**
     * Escopo para filtrar por estado.
     */
    public function scopeByEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Escopo para filtrar por cidade.
     */
    public function scopeByCidade($query, string $cidade)
    {
        return $query->where('cidade', $cidade);
    }

    /**
     * Escopo para buscar por nome.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where('nome', 'like', "%{$term}%")
            ->orWhere('cidade', 'like', "%{$term}%")
            ->orWhere('estado', 'like', "%{$term}%");
    }
}
