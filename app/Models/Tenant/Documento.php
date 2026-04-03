<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    use HasFactory;

    /**
     * O método "booted" do modelo.
     */
    protected static function booted(): void
    {
        static::saved(function (Documento $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:documentos"])->flush();
        });

        static::deleted(function (Documento $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:documentos"])->flush();
        });
    }

    protected $table = 'terreno_documentos';

    protected $fillable = [
        'terreno_id',
        'nome',
        'tipo',
        'categoria',
        'descricao',
        'file_path',
        'tamanho',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Escopos
    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }

    public function scopeAprovados($query)
    {
        return $query->where('status', 'aprovado');
    }

    public function scopeRejeitados($query)
    {
        return $query->where('status', 'rejeitado');
    }

    public function scopePorCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // Accessors (Acessores)
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pendente' => 'Pendente',
            'aprovado' => 'Aprovado',
            'rejeitado' => 'Rejeitado',
            default => 'Pendente'
        };
    }

    public function getTipoLabelAttribute(): string
    {
        $tipos = [
            'escritura' => 'Escritura',
            'matricula' => 'Matrícula',
            'certidao_negativa' => 'Certidão Negativa',
            'iptu' => 'IPTU',
            'planta' => 'Planta/Projeto',
            'levantamento_topografico' => 'Levantamento Topográfico',
            'laudo_ambiental' => 'Laudo Ambiental',
            'viabilidade' => 'Estudo de Viabilidade',
            'contrato' => 'Contrato',
            'procuracao' => 'Procuração',
            'rg_cpf' => 'RG/CPF',
            'comprovante_residencia' => 'Comprovante de Residência',
            'outros' => 'Outros',
        ];

        return $tipos[$this->tipo] ?? 'Outros';
    }

    public function getCategoriaLabelAttribute(): string
    {
        $categorias = [
            'juridico' => 'Jurídico',
            'tecnico' => 'Técnico',
            'financeiro' => 'Financeiro',
            'ambiental' => 'Ambiental',
            'pessoal' => 'Pessoal',
        ];

        return $categorias[$this->categoria] ?? 'Outros';
    }
}
