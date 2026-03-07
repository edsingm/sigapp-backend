<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerrenoStatus extends Model
{
    /** @use HasFactory<\Database\Factories\TerrenoStatusFactory> */
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (TerrenoStatus $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terreno_status"])->flush();
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:dashboard"])->flush();
        });

        static::deleted(function (TerrenoStatus $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terreno_status"])->flush();
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:dashboard"])->flush();
        });
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'terreno_status';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome',
        'cor',
        'descricao',
        'ativo',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ativo' => 'boolean',
    ];

    /**
     * Scope para buscar apenas status ativos.
     */
    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope para buscar por nome.
     */
    public function scopeBuscarPorNome($query, $nome)
    {
        return $query->where('nome', 'like', '%' . $nome . '%');
    }
}
