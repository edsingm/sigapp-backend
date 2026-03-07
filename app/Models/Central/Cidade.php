<?php

namespace App\Models\Central;

use App\Models\Tenant\Terreno;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Cidade extends Model
{
    use HasFactory, CentralConnection;

    /**
     * The table associated with the model.
     */
    protected $table = 'cidades';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'city',
        'state',
        'state_code',
        'latitude',
        'longitude',
        'capital',
        'area_code',
        'timezone',
        'population',
        'employed',
        'per_capta_income',
        'property_maximum_value',
        'buyer_demand',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'capital' => 'boolean',
        'population' => 'integer',
        'employed' => 'integer',
        'per_capta_income' => 'decimal:2',
        'property_maximum_value' => 'decimal:2',
        'buyer_demand' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the validation rules for the model.
     */
    public static function rules($id = null): array
    {
        return [
            'code' => 'required|string|max:255|unique:cidades,code' . ($id ? ',' . $id : ''),
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'state_code' => 'required|string|size:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'capital' => 'boolean',
            'area_code' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'population' => 'nullable|integer|min:0',
            'employed' => 'nullable|integer|min:0',
            'per_capta_income' => 'nullable|numeric|min:0',
            'property_maximum_value' => 'nullable|numeric|min:0',
            'buyer_demand' => 'nullable|numeric|between:0,100',
        ];
    }

    /**
     * Relacionamento com áreas vinculadas à cidade (por código)
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Terreno::class, 'cidade_code', 'code');
    }

    // Scope para buscar estados únicos (por state_code)
    public function scopeStates($query)
    {
        return $query->select('state_code', 'state')
            ->distinct()
            ->orderBy('state');
    }

    // Scope para buscar cidades de um estado específico (por state_code)
    public function scopeCitiesByState($query, $stateCode)
    {
        return $query->where('state_code', $stateCode)
            ->orderBy('city');
    }

    /**
     * Accessor para nome (compatibilidade com templates antigos)
     */
    public function getNomeAttribute()
    {
        return $this->city;
    }

    /**
     * Accessor para estado (compatibilidade com templates antigos)
     */
    public function getEstadoAttribute()
    {
        return (object) [
            'sigla' => $this->state_code,
            'nome' => $this->state
        ];
    }
}
