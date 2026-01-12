<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metric extends Model
{
    protected $fillable = [
        'user_id',
        'metric_type',
        'value',
        'model',
        'session_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:6',
            'recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Metric type constants
    public const TYPE_TOKENS_INPUT = 'tokens_input';
    public const TYPE_TOKENS_OUTPUT = 'tokens_output';
    public const TYPE_TOKENS_CACHE_READ = 'tokens_cache_read';
    public const TYPE_TOKENS_CACHE_CREATION = 'tokens_cache_creation';
    public const TYPE_COST = 'cost';
    public const TYPE_LINES_ADDED = 'lines_added';
    public const TYPE_LINES_REMOVED = 'lines_removed';
    public const TYPE_COMMITS = 'commits';
    public const TYPE_PULL_REQUESTS = 'pull_requests';
    public const TYPE_SESSIONS = 'sessions';
    public const TYPE_ACTIVE_TIME = 'active_time';
    public const TYPE_TOOL_INVOCATIONS = 'tool_invocations';
}
