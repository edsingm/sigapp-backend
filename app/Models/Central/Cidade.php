<?php

namespace App\Models\Central;

use App\Models\Tenant\Terreno;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Table('cidades')]
#[Fillable(['code', 'city', 'state', 'state_code', 'latitude', 'longitude', 'capital', 'area_code', 'timezone', 'population', 'employed', 'per_capta_income', 'property_maximum_value', 'buyer_demand', 'own_property', 'rented_property'])]
#[Hidden([])]
class Cidade extends Model
{
    use CentralConnection, HasFactory;

    /**
     * @var array<string, string>
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
        'own_property' => 'integer',
        'rented_property' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtém as regras de validação para o modelo.
     */
    public static function rules($id = null): array
    {
        return [
            'code' => 'required|string|max:255|unique:cidades,code'.($id ? ','.$id : ''),
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
            'own_property' => 'nullable|integer|min:0',
            'rented_property' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Relacionamento com áreas vinculadas à cidade (por código)
     *
     * @return HasMany<Terreno, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Terreno::class, 'cidade_code', 'code');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeStates(Builder $query): void
    {
        $query->select(['state_code', 'state'])
            ->distinct()
            ->orderBy('state');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCitiesByState(Builder $query, string $stateCode): void
    {
        $query->where('state_code', $stateCode)
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
            'nome' => $this->state,
        ];
    }
}
