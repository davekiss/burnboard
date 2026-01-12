<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLevel;

class LevelService
{
    /**
     * Update a user's level based on new tokens.
     * Returns array of level-ups that occurred.
     *
     * @return array{level_ups: array, tier_ups: array}
     */
    public function addTokens(User $user, int $tokens): array
    {
        $userLevel = $user->level ?? $this->createInitialLevel($user);

        $oldTier = $userLevel->tier;
        $oldLevel = $userLevel->level;

        $userLevel->total_tokens += $tokens;
        $userLevel->tier_tokens += $tokens;

        $levelUps = [];
        $tierUps = [];

        // Check for level-ups and tier-ups
        while (true) {
            $newLevel = $this->calculateLevelForTokens($userLevel->tier_tokens);

            if ($newLevel > $userLevel->level && $userLevel->level < 10) {
                // Level up within tier
                $userLevel->level = min($newLevel, 10);
                $userLevel->last_level_up_at = now();
                $levelUps[] = [
                    'tier' => $userLevel->tier,
                    'level' => $userLevel->level,
                    'tier_name' => $userLevel->tier_name,
                ];
            }

            // Check for tier-up (at level 10 with enough tokens)
            if ($userLevel->level >= 10 && $userLevel->tier_tokens >= UserLevel::TOKENS_PER_TIER) {
                $userLevel->tier += 1;
                $userLevel->level = 1;
                $userLevel->tier_tokens -= UserLevel::TOKENS_PER_TIER;
                $userLevel->last_tier_up_at = now();
                $userLevel->last_level_up_at = now();

                $tierUps[] = [
                    'tier' => $userLevel->tier,
                    'tier_name' => $userLevel->tier_name,
                ];

                // Continue checking for more level-ups in new tier
                continue;
            }

            break;
        }

        $userLevel->save();

        return [
            'level_ups' => $levelUps,
            'tier_ups' => $tierUps,
            'current' => [
                'tier' => $userLevel->tier,
                'tier_name' => $userLevel->tier_name,
                'level' => $userLevel->level,
                'display_name' => $userLevel->display_name,
                'total_tokens' => $userLevel->total_tokens,
                'progress_to_next' => $userLevel->progress_to_next_level,
            ],
        ];
    }

    /**
     * Get or create a user's level record.
     */
    public function getOrCreateLevel(User $user): UserLevel
    {
        return $user->level ?? $this->createInitialLevel($user);
    }

    /**
     * Recalculate a user's level from their total token count.
     * Useful for backfilling or correcting data.
     */
    public function recalculateLevel(User $user, int $totalTokens): UserLevel
    {
        $userLevel = $user->level ?? new UserLevel(['user_id' => $user->id]);

        $userLevel->total_tokens = $totalTokens;

        // Calculate tier and remaining tokens
        $tier = 1;
        $remainingTokens = $totalTokens;

        while ($remainingTokens >= UserLevel::TOKENS_PER_TIER) {
            $tier++;
            $remainingTokens -= UserLevel::TOKENS_PER_TIER;
        }

        $userLevel->tier = $tier;
        $userLevel->tier_tokens = $remainingTokens;
        $userLevel->level = $this->calculateLevelForTokens($remainingTokens);

        $userLevel->save();

        return $userLevel;
    }

    /**
     * Calculate the level for a given number of tokens within a tier.
     */
    private function calculateLevelForTokens(int $tierTokens): int
    {
        $level = 1;

        foreach (UserLevel::LEVEL_THRESHOLDS as $lvl => $threshold) {
            if ($tierTokens >= $threshold) {
                $level = $lvl;
            } else {
                break;
            }
        }

        return min($level, 10);
    }

    /**
     * Create an initial level record for a user.
     */
    private function createInitialLevel(User $user): UserLevel
    {
        return UserLevel::create([
            'user_id' => $user->id,
            'tier' => 1,
            'level' => 1,
            'total_tokens' => 0,
            'tier_tokens' => 0,
        ]);
    }
}
