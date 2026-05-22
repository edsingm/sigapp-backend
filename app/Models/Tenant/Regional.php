<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('regionais')]
#[Fillable(['nome', 'estado', 'cidade', 'endereco', 'numero', 'telefone', 'celular', 'observacoes', 'responsavel_id', 'created_by', 'updated_by'])]
class Regional extends Model
{
    use HasFactory, SoftDeletes;

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
