<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardPosition extends Model
{
    protected $fillable = [
        'user_id',
        'period',
        'position',
        'achieved_at',
        'lost_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'achieved_at' => 'datetime',
            'lost_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public function scopeCurrentlyHeld($query)
    {
        return $query->whereNull('lost_at');
    }

    public function scopePosition($query, int $position)
    {
        return $query->where('position', $position);
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (! $this->duration_ms) {
            if ($this->lost_at === null && $this->achieved_at) {
                // Still holding - calculate live duration
                $ms = now()->diffInMilliseconds($this->achieved_at);
            } else {
                return null;
            }
        } else {
            $ms = $this->duration_ms;
        }

        $seconds = floor($ms / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);

        $remainingMs = $ms % 1000;
        $remainingSeconds = $seconds % 60;
        $remainingMinutes = $minutes % 60;
        $remainingHours = $hours % 24;

        $parts = [];

        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($remainingHours > 0) {
            $parts[] = "{$remainingHours}h";
        }
        if ($remainingMinutes > 0) {
            $parts[] = "{$remainingMinutes}m";
        }
        if ($remainingSeconds > 0 || empty($parts)) {
            $parts[] = "{$remainingSeconds}.{$remainingMs}s";
        }

        return implode(' ', $parts);
    }

    // Period constants
    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    public const PERIOD_ALL = 'all';
}
