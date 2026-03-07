<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorretorExterno extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (CorretorExterno $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:corretores_externos"])->flush();
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terrenos"])->flush();
        });

        static::deleted(function (CorretorExterno $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:corretores_externos"])->flush();
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terrenos"])->flush();
        });
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'corretores_externos';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'creci',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'creci' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the validation rules for the model.
     */
    public static function rules($id = null): array
    {
        return [
            'nome' => 'required|string|max:255',
            'email' => 'required|email|unique:corretores_externos,email' . ($id ? ',' . $id : ''),
            'telefone' => 'required|string|max:20',
            'creci' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get the validation messages for the model.
     */
    public static function messages(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'nome.string' => 'O nome deve ser um texto válido.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'O email deve ter um formato válido.',
            'email.unique' => 'Este email já está sendo usado por outro corretor.',
            'telefone.required' => 'O telefone é obrigatório.',
            'telefone.string' => 'O telefone deve ser um texto válido.',
            'telefone.max' => 'O telefone não pode ter mais de 20 caracteres.',
            'creci.integer' => 'O CRECI deve ser um número válido.',
            'creci.min' => 'O CRECI deve ser um número positivo.',
        ];
    }

    /**
     * Scope a query to search corretores by name or email.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nome', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('telefone', 'like', "%{$search}%")
                ->orWhere('creci', 'like', "%{$search}%");
        });
    }

    /**
     * Get the formatted CRECI number.
     */
    public function getCreciFormatadoAttribute(): ?string
    {
        return $this->creci ? 'CRECI: ' . $this->creci : null;
    }

    /**
     * Get the formatted phone number.
     */
    public function getTelefoneFormatadoAttribute(): string
    {
        $telefone = preg_replace('/\D/', '', $this->telefone);

        if (strlen($telefone) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
        } elseif (strlen($telefone) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
        }

        return $this->telefone;
    }
}