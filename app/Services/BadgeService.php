<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\LeaderboardPosition;
use App\Models\Metric;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BadgeService
{
    /**
     * Check and award any badges a user has earned.
     * Returns array of newly awarded badges.
     *
     * @return array<array{badge: Badge, metadata: array}>
     */
    public function checkAndAwardBadges(User $user): array
    {
        $earnedBadgeIds = $user->badges()->pluck('badges.id')->toArray();

        $availableBadges = Badge::active()
            ->whereNotIn('id', $earnedBadgeIds)
            ->get();

        $newlyAwarded = [];
        $userStats = $this->calculateUserStats($user);

        foreach ($availableBadges as $badge) {
            $result = $this->checkBadgeRequirements($badge, $user, $userStats);

            if ($result['earned']) {
                $this->awardBadge($user, $badge, $result['metadata']);
                $newlyAwarded[] = [
                    'badge' => $badge,
                    'metadata' => $result['metadata'],
                ];
            }
        }

        return $newlyAwarded;
    }

    /**
     * Award a specific badge to a user.
     */
    public function awardBadge(User $user, Badge $badge, array $metadata = []): UserBadge
    {
        return UserBadge::create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Check if a user has earned a specific badge.
     */
    public function hasBadge(User $user, string $slug): bool
    {
        return $user->badges()->where('slug', $slug)->exists();
    }

    /**
     * Get all badges for a user, organized by category.
     *
     * @return Collection<string, Collection<Badge>>
     */
    public function getUserBadgesByCategory(User $user): Collection
    {
        return $user->badges()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');
    }

    /**
     * Calculate aggregated stats for badge checking.
     *
     * @return array<string, mixed>
     */
    private function calculateUserStats(User $user): array
    {
        $metrics = $user->metrics();

        // Token stats
        $totalTokensInput = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_TOKENS_INPUT)
            ->sum('value');

        $totalTokensOutput = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_TOKENS_OUTPUT)
            ->sum('value');

        $totalTokensCacheRead = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_TOKENS_CACHE_READ)
            ->sum('value');

        $totalTokensCacheCreation = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_TOKENS_CACHE_CREATION)
            ->sum('value');

        $totalTokens = $totalTokensInput + $totalTokensOutput + $totalTokensCacheRead + $totalTokensCacheCreation;

        // Cache efficiency
        $cacheRate = $totalTokens > 0
            ? ($totalTokensCacheRead / $totalTokens) * 100
            : 0;

        // Cost stats
        $totalCost = (float) $metrics->clone()
            ->where('metric_type', Metric::TYPE_COST)
            ->sum('value');

        // Code stats
        $totalLinesAdded = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_LINES_ADDED)
            ->sum('value');

        $totalLinesRemoved = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_LINES_REMOVED)
            ->sum('value');

        $totalCommits = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_COMMITS)
            ->sum('value');

        $totalPullRequests = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_PULL_REQUESTS)
            ->sum('value');

        // Session stats
        $totalSessions = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_SESSIONS)
            ->sum('value');

        $totalActiveMinutes = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_ACTIVE_TIME)
            ->sum('value');

        $totalToolInvocations = (int) $metrics->clone()
            ->where('metric_type', Metric::TYPE_TOOL_INVOCATIONS)
            ->sum('value');

        // Streak calculation
        $streak = $this->calculateStreak($user);

        // Time-based stats
        $timeStats = $this->calculateTimeStats($user);

        return [
            'total_tokens' => $totalTokens,
            'total_tokens_input' => $totalTokensInput,
            'total_tokens_output' => $totalTokensOutput,
            'total_tokens_cache_read' => $totalTokensCacheRead,
            'total_tokens_cache_creation' => $totalTokensCacheCreation,
            'cache_rate' => $cacheRate,
            'total_cost' => $totalCost,
            'total_lines_added' => $totalLinesAdded,
            'total_lines_removed' => $totalLinesRemoved,
            'net_lines' => $totalLinesAdded - $totalLinesRemoved,
            'total_commits' => $totalCommits,
            'total_pull_requests' => $totalPullRequests,
            'total_sessions' => $totalSessions,
            'total_active_minutes' => $totalActiveMinutes,
            'total_tool_invocations' => $totalToolInvocations,
            'current_streak' => $streak['current'],
            'longest_streak' => $streak['longest'],
            'night_sessions' => $timeStats['night_sessions'],
            'early_sessions' => $timeStats['early_sessions'],
            'weekend_sessions' => $timeStats['weekend_sessions'],
        ];
    }

    /**
     * Calculate streak data for a user.
     *
     * @return array{current: int, longest: int}
     */
    private function calculateStreak(User $user): array
    {
        $activeDays = $user->metrics()
            ->selectRaw('DATE(recorded_at) as active_date')
            ->groupBy('active_date')
            ->orderBy('active_date', 'desc')
            ->pluck('active_date')
            ->map(fn ($date) => Carbon::parse($date))
            ->values();

        if ($activeDays->isEmpty()) {
            return ['current' => 0, 'longest' => 0];
        }

        $currentStreak = 0;
        $longestStreak = 0;
        $tempStreak = 1;

        // Check if streak is still active (today or yesterday)
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $firstDay = $activeDays->first();

        $streakActive = $firstDay->isSameDay($today) || $firstDay->isSameDay($yesterday);

        if ($streakActive) {
            $currentStreak = 1;
        }

        // Calculate streaks
        for ($i = 0; $i < $activeDays->count() - 1; $i++) {
            $current = $activeDays[$i];
            $next = $activeDays[$i + 1];

            // Check if dates are consecutive (1 day apart)
            $daysDiff = abs((int) $current->startOfDay()->diffInDays($next->startOfDay()));

            if ($daysDiff === 1) {
                $tempStreak++;
                if ($streakActive && $i === 0) {
                    $currentStreak = $tempStreak;
                }
            } else {
                $longestStreak = max($longestStreak, $tempStreak);
                $tempStreak = 1;
                if ($i === 0) {
                    $streakActive = false;
                }
            }
        }

        $longestStreak = max($longestStreak, $tempStreak);
        if ($streakActive) {
            $currentStreak = max($currentStreak, $tempStreak);
        }

        return [
            'current' => $currentStreak,
            'longest' => $longestStreak,
        ];
    }

    /**
     * Calculate time-based stats for badges.
     *
     * @return array{night_sessions: int, early_sessions: int, weekend_sessions: int}
     */
    private function calculateTimeStats(User $user): array
    {
        $metrics = $user->metrics()
            ->where('metric_type', Metric::TYPE_SESSIONS)
            ->get();

        $nightSessions = 0;   // 10pm - 4am
        $earlySessions = 0;   // 5am - 7am
        $weekendSessions = 0; // Saturday or Sunday

        foreach ($metrics as $metric) {
            $recordedAt = $metric->recorded_at;
            $hour = $recordedAt->hour;
            $dayOfWeek = $recordedAt->dayOfWeek;

            if ($hour >= 22 || $hour <= 4) {
                $nightSessions += (int) $metric->value;
            }

            if ($hour >= 5 && $hour <= 7) {
                $earlySessions += (int) $metric->value;
            }

            if ($dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY) {
                $weekendSessions += (int) $metric->value;
            }
        }

        return [
            'night_sessions' => $nightSessions,
            'early_sessions' => $earlySessions,
            'weekend_sessions' => $weekendSessions,
        ];
    }

    /**
     * Check if a user meets a badge's requirements.
     *
     * @return array{earned: bool, metadata: array}
     */
    private function checkBadgeRequirements(Badge $badge, User $user, array $stats): array
    {
        $requirements = $badge->requirements ?? [];

        if (empty($requirements)) {
            return ['earned' => false, 'metadata' => []];
        }

        $type = $requirements['type'] ?? null;

        return match ($type) {
            // Milestone badges
            'total_tokens' => $this->checkThreshold($stats['total_tokens'], $requirements),
            'total_cost' => $this->checkThreshold($stats['total_cost'], $requirements),
            'total_commits' => $this->checkThreshold($stats['total_commits'], $requirements),
            'total_pull_requests' => $this->checkThreshold($stats['total_pull_requests'], $requirements),
            'total_lines_added' => $this->checkThreshold($stats['total_lines_added'], $requirements),
            'net_lines' => $this->checkThreshold($stats['net_lines'], $requirements),
            'total_sessions' => $this->checkThreshold($stats['total_sessions'], $requirements),
            'total_active_minutes' => $this->checkThreshold($stats['total_active_minutes'], $requirements),
            'total_tool_invocations' => $this->checkThreshold($stats['total_tool_invocations'], $requirements),

            // Efficiency badges
            'cache_rate' => $this->checkThreshold($stats['cache_rate'], $requirements),

            // Streak badges
            'current_streak' => $this->checkThreshold($stats['current_streak'], $requirements),
            'longest_streak' => $this->checkThreshold($stats['longest_streak'], $requirements),

            // Time-based badges
            'night_sessions' => $this->checkThreshold($stats['night_sessions'], $requirements),
            'early_sessions' => $this->checkThreshold($stats['early_sessions'], $requirements),
            'weekend_sessions' => $this->checkThreshold($stats['weekend_sessions'], $requirements),

            // Competitive badges
            'leaderboard_position' => $this->checkLeaderboardPosition($user, $requirements),
            'leaderboard_hold_time' => $this->checkLeaderboardHoldTime($user, $requirements),

            // Code surgery (more lines removed than added)
            'net_negative_lines' => $this->checkNetNegativeLines($stats, $requirements),

            default => ['earned' => false, 'metadata' => []],
        };
    }

    /**
     * Check if a value meets or exceeds a threshold.
     *
     * @return array{earned: bool, metadata: array}
     */
    private function checkThreshold(float|int $value, array $requirements): array
    {
        $threshold = $requirements['threshold'] ?? 0;
        $earned = $value >= $threshold;

        return [
            'earned' => $earned,
            'metadata' => $earned ? ['value' => $value, 'threshold' => $threshold] : [],
        ];
    }

    /**
     * Check leaderboard position requirements.
     *
     * @return array{earned: bool, metadata: array}
     */
    private function checkLeaderboardPosition(User $user, array $requirements): array
    {
        $position = $requirements['position'] ?? 1;
        $period = $requirements['period'] ?? LeaderboardPosition::PERIOD_ALL;

        $hasPosition = LeaderboardPosition::where('user_id', $user->id)
            ->forPeriod($period)
            ->position($position)
            ->exists();

        return [
            'earned' => $hasPosition,
            'metadata' => $hasPosition ? ['position' => $position, 'period' => $period] : [],
        ];
    }

    /**
     * Check leaderboard hold time requirements.
     *
     * @return array{earned: bool, metadata: array}
     */
    private function checkLeaderboardHoldTime(User $user, array $requirements): array
    {
        $minDurationMs = $requirements['min_duration_ms'] ?? 0;
        $position = $requirements['position'] ?? 1;
        $period = $requirements['period'] ?? LeaderboardPosition::PERIOD_ALL;

        // Get total time held at position
        $totalHoldTime = LeaderboardPosition::where('user_id', $user->id)
            ->forPeriod($period)
            ->position($position)
            ->get()
            ->sum(function ($record) {
                if ($record->duration_ms) {
                    return $record->duration_ms;
                }
                // Still holding - calculate live duration
                if ($record->lost_at === null && $record->achieved_at) {
                    return now()->diffInMilliseconds($record->achieved_at);
                }

                return 0;
            });

        $earned = $totalHoldTime >= $minDurationMs;

        return [
            'earned' => $earned,
            'metadata' => $earned ? [
                'total_hold_time_ms' => $totalHoldTime,
                'position' => $position,
                'period' => $period,
            ] : [],
        ];
    }

    /**
     * Check for net negative lines (more removed than added).
     *
     * @return array{earned: bool, metadata: array}
     */
    private function checkNetNegativeLines(array $stats, array $requirements): array
    {
        $minRemoved = $requirements['min_removed'] ?? 1000;
        $netLines = $stats['net_lines'];
        $linesRemoved = $stats['total_lines_removed'];

        $earned = $netLines < 0 && $linesRemoved >= $minRemoved;

        return [
            'earned' => $earned,
            'metadata' => $earned ? [
                'lines_removed' => $linesRemoved,
                'lines_added' => $stats['total_lines_added'],
                'net_lines' => $netLines,
            ] : [],
        ];
    }

    /**
     * Manually check and award a specific badge by slug.
     * Useful for hidden badges triggered by special events.
     */
    public function awardBadgeBySlug(User $user, string $slug, array $metadata = []): ?UserBadge
    {
        if ($this->hasBadge($user, $slug)) {
            return null;
        }

        $badge = Badge::where('slug', $slug)->first();

        if (! $badge || ! $badge->is_active) {
            return null;
        }

        return $this->awardBadge($user, $badge, $metadata);
    }
}
