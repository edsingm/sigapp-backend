<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiRequestLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_request_logs';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'duration_ms',
        'tool_calls_count',
        'tool_calls',
        'status',
        'error_message',
        'ip_address',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost_usd' => 'decimal:6',
        'duration_ms' => 'integer',
        'tool_calls_count' => 'integer',
        'user_id' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
