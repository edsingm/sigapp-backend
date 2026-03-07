<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;


class Regional extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (Regional $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:regionais"])->flush();
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terrenos"])->flush();
        });

        static::deleted(function (Regional $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:regionais"])->flush();
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terrenos"])->flush();
        });

        static::restored(function (Regional $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:regionais"])->flush();
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terrenos"])->flush();
        });
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'regionais';

    /**
     * The attributes that are mass assignable.
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the responsible user for this regional.
     */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    /**
     * Get the user who created this regional.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this regional.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the full address.
     */
    public function getEnderecoCompletoAttribute(): string
    {
        return "{$this->endereco}, {$this->numero} - {$this->cidade}, {$this->estado}";
    }

    /**
     * Scope to filter by state.
     */
    public function scopeByEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope to filter by city.
     */
    public function scopeByCidade($query, string $cidade)
    {
        return $query->where('cidade', $cidade);
    }

    /**
     * Scope to search by name.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where('nome', 'like', "%{$term}%")
            ->orWhere('cidade', 'like', "%{$term}%")
            ->orWhere('estado', 'like', "%{$term}%");
    }
}
