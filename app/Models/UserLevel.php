<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLevel extends Model
{
    protected $fillable = [
        'user_id',
        'tier',
        'level',
        'total_tokens',
        'tier_tokens',
        'last_level_up_at',
        'last_tier_up_at',
    ];

    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'level' => 'integer',
            'total_tokens' => 'integer',
            'tier_tokens' => 'integer',
            'last_level_up_at' => 'datetime',
            'last_tier_up_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Tier names (infinite - after defined names, use Roman numerals)
    public const TIER_NAMES = [
        1 => 'Spark',
        2 => 'Ember',
        3 => 'Flame',
        4 => 'Blaze',
        5 => 'Inferno',
        6 => 'Supernova',
        7 => 'Cosmic',
        8 => 'Eternal',
        9 => 'Mythic',
        10 => 'Transcendent',
    ];

    // Level thresholds within each tier (tokens required)
    public const LEVEL_THRESHOLDS = [
        1 => 0,          // Level 1: 0 tokens
        2 => 250_000,    // Level 2: 250K
        3 => 500_000,    // Level 3: 500K
        4 => 1_000_000,  // Level 4: 1M
        5 => 2_500_000,  // Level 5: 2.5M
        6 => 5_000_000,  // Level 6: 5M
        7 => 10_000_000, // Level 7: 10M
        8 => 25_000_000, // Level 8: 25M
        9 => 50_000_000, // Level 9: 50M
        10 => 100_000_000, // Level 10: 100M
    ];

    // Tokens needed to complete a tier (reach level 10 and tier up)
    public const TOKENS_PER_TIER = 250_000_000; // 250M

    public function getTierNameAttribute(): string
    {
        if (isset(self::TIER_NAMES[$this->tier])) {
            return self::TIER_NAMES[$this->tier];
        }

        // For tiers beyond 10, use "Transcendent II", "Transcendent III", etc.
        $extraTiers = $this->tier - 10;

        return 'Transcendent '.self::toRomanNumerals($extraTiers + 1);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->tier_name} • Level {$this->level}";
    }

    public function getProgressToNextLevelAttribute(): float
    {
        if ($this->level >= 10) {
            // At max level in tier, show progress to tier-up
            $tokensNeeded = self::TOKENS_PER_TIER;
            $tokensInTier = $this->tier_tokens;

            return min(100, ($tokensInTier / $tokensNeeded) * 100);
        }

        $currentThreshold = self::LEVEL_THRESHOLDS[$this->level];
        $nextThreshold = self::LEVEL_THRESHOLDS[$this->level + 1];
        $tokensInLevel = $this->tier_tokens - $currentThreshold;
        $tokensNeeded = $nextThreshold - $currentThreshold;

        return min(100, ($tokensInLevel / $tokensNeeded) * 100);
    }

    public function getTokensToNextLevelAttribute(): int
    {
        if ($this->level >= 10) {
            return max(0, self::TOKENS_PER_TIER - $this->tier_tokens);
        }

        $nextThreshold = self::LEVEL_THRESHOLDS[$this->level + 1];

        return max(0, $nextThreshold - $this->tier_tokens);
    }

    private static function toRomanNumerals(int $number): string
    {
        $map = [
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1,
        ];

        $result = '';
        foreach ($map as $roman => $value) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
    }
}
