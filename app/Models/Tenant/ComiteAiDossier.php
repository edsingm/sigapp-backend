<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\ComiteAiDossierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $comite_revisao_id
 * @property int $terreno_id
 * @property int|null $viabilidade_id
 * @property string $status
 * @property int $prompt_version
 * @property string $input_hash
 * @property array<string, string|null>|null $sections
 * @property string|null $raw_response
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $generated_by
 * @property Carbon|null $generated_at
 * @property string|null $error_message
 */
#[Table('comite_ai_dossiers')]
#[Fillable(['comite_revisao_id', 'terreno_id', 'viabilidade_id', 'status', 'prompt_version', 'input_hash', 'sections', 'raw_response', 'provider', 'model', 'generated_by', 'generated_at', 'error_message'])]
class ComiteAiDossier extends Model
{
    /** @use HasFactory<ComiteAiDossierFactory> */
    use HasFactory;

    protected $casts = [
        'sections' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ComiteRevisao, $this>
     */
    public function comiteRevisao(): BelongsTo
    {
        return $this->belongsTo(ComiteRevisao::class, 'comite_revisao_id');
    }

    /**
     * @return BelongsTo<Terreno, $this>
     */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    /**
     * @return BelongsTo<Viabilidade, $this>
     */
    public function viabilidade(): BelongsTo
    {
        return $this->belongsTo(Viabilidade::class, 'viabilidade_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
