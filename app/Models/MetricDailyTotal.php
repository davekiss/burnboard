<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Total of one metric type for one user, day and model, kept up to date on ingest.
 * Metrics without a model are stored with an empty model.
 */
class MetricDailyTotal extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'metric_type',
        'model',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
