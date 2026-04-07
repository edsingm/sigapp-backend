<?php

namespace App\Models\Tenant;

use App\Traits\HasDashboardCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proprietario extends Model
{
    use HasDashboardCache, HasFactory, SoftDeletes;

    protected $table = 'terreno_proprietarios';

    /**
     * O método "booted" do modelo.
     */
    protected static function booted(): void
    {
        static::saved(function (Proprietario $proprietario) {
            $proprietario->clearTenantCache('proprietarios');
        });

        static::deleted(function (Proprietario $proprietario) {
            $proprietario->clearTenantCache('proprietarios');
        });

        static::restored(function (Proprietario $proprietario) {
            $proprietario->clearTenantCache('proprietarios');
        });
    }

    /**
     * Constantes para o tipo de pessoa
     */
    const TIPO_FISICA = 'fisica';

    const TIPO_JURIDICA = 'juridica';

    protected $fillable = [
        'terreno_id',
        'nome',
        'rg',
        'cpf_cnpj',
        'nascimento',
        'tipo_pessoa',
        'estado_civil',
        'nacionalidade',
        'profissao',
        'porcentagem_terreno',
        'email',
        'telefone',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'conjuge',
        'conjuge_rg',
        'conjuge_nascimento',
        'conjuge_cpf_cnpj',
        'observacoes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tipo_pessoa' => 'string',
        'nascimento' => 'date',
        'conjuge_nascimento' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relacionamento com o Terreno
     */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    /**
     * Relacionamento com o Usuário (criador)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relacionamento com o Usuário (atualizador)
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Auxiliar para formatar CPF ou CNPJ
     */
    private function formatarCpfCnpj($value, $tipo): string
    {
        if (empty($value)) {
            return '';
        }

        $cleaned = preg_replace('/\D/', '', $value);

        if ($tipo === self::TIPO_FISICA) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cleaned);
        } else {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cleaned);
        }
    }

    /**
     * Acessor para formatar CPF/CNPJ
     */
    public function getCpfCnpjFormatadoAttribute(): string
    {
        return $this->formatarCpfCnpj($this->cpf_cnpj, $this->tipo_pessoa);
    }

    /**
     * Acessor para formatar CPF/CNPJ do cônjuge
     */
    public function getConjugeCpfCnpjFormatadoAttribute(): string
    {
        return $this->formatarCpfCnpj($this->conjuge_cpf_cnpj, self::TIPO_FISICA);
    }

    /**
     * Acessor para formatar telefone
     */
    public function getTelefoneFormatadoAttribute(): string
    {
        if (empty($this->telefone)) {
            return '';
        }

        $telefone = preg_replace('/\D/', '', $this->telefone);

        if (strlen($telefone) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
        } elseif (strlen($telefone) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
        }

        return $this->telefone;
    }
}
