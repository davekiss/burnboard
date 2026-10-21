<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Progress of the one-off backfill of metric rollups from rows recorded before
 * rollups existed: rows with ids up to cutoff_id, summed through last_id so far.
 */
class MetricRollupBackfill extends Model
{
    public const METRICS = 'metrics';

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'cutoff_id',
        'last_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_id' => 'integer',
            'last_id' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
