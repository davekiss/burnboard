<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * All-time total of one metric type for one user, kept up to date on ingest.
 */
class MetricTotal extends Model
{
    protected $fillable = [
        'user_id',
        'metric_type',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Derived session counters, bucketed by the hour and day each session was recorded
    public const TYPE_NIGHT_SESSIONS = 'sessions_night';

    public const TYPE_EARLY_SESSIONS = 'sessions_early';

    public const TYPE_WEEKEND_SESSIONS = 'sessions_weekend';
}
