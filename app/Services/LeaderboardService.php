<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    public function getLeaderboard(string $period = 'week', int $limit = 100): Collection
    {
        $startDate = $this->getStartDate($period);

        return User::query()
            ->select([
                'users.id',
                'users.github_username',
                'users.avatar_url',
                'users.is_verified',
                'users.verification_score',
                DB::raw('COALESCE(SUM(CASE WHEN metrics.metric_type IN (\'tokens_input\', \'tokens_output\', \'tokens_cache_read\', \'tokens_cache_creation\') THEN metrics.value ELSE 0 END), 0) as total_tokens'),
                DB::raw('COALESCE(SUM(CASE WHEN metrics.metric_type = \'cost\' THEN metrics.value ELSE 0 END), 0) as total_cost'),
                DB::raw('COALESCE(SUM(CASE WHEN metrics.metric_type = \'lines_added\' THEN metrics.value ELSE 0 END), 0) as lines_added'),
                DB::raw('COALESCE(SUM(CASE WHEN metrics.metric_type = \'lines_removed\' THEN metrics.value ELSE 0 END), 0) as lines_removed'),
                DB::raw('COALESCE(SUM(CASE WHEN metrics.metric_type = \'commits\' THEN metrics.value ELSE 0 END), 0) as commits'),
                DB::raw('COALESCE(SUM(CASE WHEN metrics.metric_type = \'pull_requests\' THEN metrics.value ELSE 0 END), 0) as pull_requests'),
                DB::raw('COALESCE(SUM(CASE WHEN metrics.metric_type = \'sessions\' THEN metrics.value ELSE 0 END), 0) as sessions'),
                DB::raw('COALESCE(SUM(CASE WHEN metrics.metric_type = \'active_time\' THEN metrics.value ELSE 0 END), 0) as active_time'),
            ])
            ->leftJoin('metrics', function ($join) use ($startDate) {
                $join->on('users.id', '=', 'metrics.user_id')
                    ->where('metrics.recorded_at', '>=', $startDate);
            })
            ->whereNotNull('users.github_username')
            ->groupBy('users.id', 'users.github_username', 'users.avatar_url', 'users.is_verified', 'users.verification_score')
            ->having('total_tokens', '>', 0)
            ->orderByDesc('total_tokens')
            ->limit($limit)
            ->get()
            ->map(fn ($user, $index) => [
                'rank' => $index + 1,
                'github_username' => $user->github_username,
                'avatar_url' => $user->avatar_url,
                'is_verified' => (bool) $user->is_verified,
                'verification_score' => (int) $user->verification_score,
                'total_tokens' => (int) $user->total_tokens,
                'total_cost' => round((float) $user->total_cost, 2),
                'lines_added' => (int) $user->lines_added,
                'lines_removed' => (int) $user->lines_removed,
                'commits' => (int) $user->commits,
                'pull_requests' => (int) $user->pull_requests,
                'sessions' => (int) $user->sessions,
                'active_time' => (int) $user->active_time,
            ]);
    }

    public function getUserStats(User $user, string $period = 'week'): array
    {
        $startDate = $this->getStartDate($period);

        $stats = Metric::query()
            ->select([
                DB::raw('COALESCE(SUM(CASE WHEN metric_type IN (\'tokens_input\', \'tokens_output\', \'tokens_cache_read\', \'tokens_cache_creation\') THEN value ELSE 0 END), 0) as total_tokens'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'tokens_input\' THEN value ELSE 0 END), 0) as input_tokens'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'tokens_output\' THEN value ELSE 0 END), 0) as output_tokens'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'tokens_cache_read\' THEN value ELSE 0 END), 0) as cache_read_tokens'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'tokens_cache_creation\' THEN value ELSE 0 END), 0) as cache_creation_tokens'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'cost\' THEN value ELSE 0 END), 0) as total_cost'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'lines_added\' THEN value ELSE 0 END), 0) as lines_added'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'lines_removed\' THEN value ELSE 0 END), 0) as lines_removed'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'commits\' THEN value ELSE 0 END), 0) as commits'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'pull_requests\' THEN value ELSE 0 END), 0) as pull_requests'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'sessions\' THEN value ELSE 0 END), 0) as sessions'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'active_time\' THEN value ELSE 0 END), 0) as active_time'),
                DB::raw('COALESCE(SUM(CASE WHEN metric_type = \'tool_invocations\' THEN value ELSE 0 END), 0) as tool_invocations'),
            ])
            ->where('user_id', $user->id)
            ->where('recorded_at', '>=', $startDate)
            ->first();

        // Get user's rank
        $rank = $this->getUserRank($user, $period);

        // Calculate derived metrics
        $inputTokens = (int) $stats->input_tokens;
        $outputTokens = (int) $stats->output_tokens;
        $cacheReadTokens = (int) $stats->cache_read_tokens;
        $cacheCreationTokens = (int) $stats->cache_creation_tokens;
        $totalCost = (float) $stats->total_cost;

        // Cache efficiency: % of input tokens served from cache
        $totalInputWithCache = $inputTokens + $cacheReadTokens + $cacheCreationTokens;
        $cacheEfficiency = $totalInputWithCache > 0
            ? round(($cacheReadTokens / $totalInputWithCache) * 100, 1)
            : 0;

        // Cost per 1K output tokens
        $costPer1kOutput = $outputTokens > 0
            ? round(($totalCost / ($outputTokens / 1000)), 4)
            : 0;

        return [
            'rank' => $rank,
            'github_username' => $user->github_username,
            'avatar_url' => $user->avatar_url,
            'is_verified' => (bool) $user->is_verified,
            'verification_score' => (int) $user->verification_score,
            'total_tokens' => (int) $stats->total_tokens,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheReadTokens,
            'cache_creation_tokens' => $cacheCreationTokens,
            'total_cost' => round($totalCost, 2),
            'cache_efficiency' => $cacheEfficiency,
            'cost_per_1k_output' => $costPer1kOutput,
            'lines_added' => (int) $stats->lines_added,
            'lines_removed' => (int) $stats->lines_removed,
            'commits' => (int) $stats->commits,
            'pull_requests' => (int) $stats->pull_requests,
            'sessions' => (int) $stats->sessions,
            'active_time' => (int) $stats->active_time,
            'tool_invocations' => (int) $stats->tool_invocations,
        ];
    }

    public function getUserRank(User $user, string $period = 'week'): ?int
    {
        $startDate = $this->getStartDate($period);

        // Get user's total tokens
        $userTokens = Metric::query()
            ->whereIn('metric_type', [
                Metric::TYPE_TOKENS_INPUT,
                Metric::TYPE_TOKENS_OUTPUT,
                Metric::TYPE_TOKENS_CACHE_READ,
                Metric::TYPE_TOKENS_CACHE_CREATION,
            ])
            ->where('user_id', $user->id)
            ->where('recorded_at', '>=', $startDate)
            ->sum('value');

        if ($userTokens == 0) {
            return null;
        }

        // Count users with more tokens
        $usersAhead = DB::table('metrics')
            ->select('user_id')
            ->whereIn('metric_type', [
                Metric::TYPE_TOKENS_INPUT,
                Metric::TYPE_TOKENS_OUTPUT,
                Metric::TYPE_TOKENS_CACHE_READ,
                Metric::TYPE_TOKENS_CACHE_CREATION,
            ])
            ->where('recorded_at', '>=', $startDate)
            ->groupBy('user_id')
            ->havingRaw('SUM(value) > ?', [$userTokens])
            ->count();

        return $usersAhead + 1;
    }

    public function getModelBreakdown(User $user, string $period = 'week'): Collection
    {
        $startDate = $this->getStartDate($period);

        return Metric::query()
            ->select([
                'model',
                DB::raw('SUM(CASE WHEN metric_type = \'tokens_input\' THEN value ELSE 0 END) as input_tokens'),
                DB::raw('SUM(CASE WHEN metric_type = \'tokens_output\' THEN value ELSE 0 END) as output_tokens'),
                DB::raw('SUM(CASE WHEN metric_type = \'cost\' THEN value ELSE 0 END) as cost'),
            ])
            ->where('user_id', $user->id)
            ->where('recorded_at', '>=', $startDate)
            ->whereNotNull('model')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get();
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            'all' => Carbon::createFromTimestamp(0),
            default => now()->startOfWeek(),
        };
    }
}
