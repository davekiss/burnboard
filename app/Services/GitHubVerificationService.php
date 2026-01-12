<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubVerificationService
{
    private string $baseUrl = 'https://api.github.com';

    /**
     * Fetch and aggregate public GitHub activity for a user.
     */
    public function fetchUserActivity(User $user, ?string $since = null): array
    {
        $username = $user->github_username;

        if (! $username) {
            return $this->emptyStats();
        }

        $since = $since ? Carbon::parse($since) : now()->subDays(30);

        // Fetch from multiple endpoints for comprehensive data
        $events = $this->fetchPublicEvents($username);
        $commits = $this->fetchRecentCommits($username, $since);

        return $this->aggregateActivity($events, $commits, $since);
    }

    /**
     * Fetch public events for a user (last 90 days, max 300 events).
     */
    private function fetchPublicEvents(string $username): array
    {
        $allEvents = [];
        $page = 1;
        $maxPages = 3; // GitHub returns 30 per page, max 10 pages

        while ($page <= $maxPages) {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/users/{$username}/events/public", [
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if (! $response->successful()) {
                Log::warning("GitHub API error for {$username}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $events = $response->json();

            if (empty($events)) {
                break;
            }

            $allEvents = array_merge($allEvents, $events);
            $page++;
        }

        return $allEvents;
    }

    /**
     * Fetch recent commits via search API.
     */
    private function fetchRecentCommits(string $username, Carbon $since): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/search/commits", [
                'q' => "author:{$username} committer-date:>={$since->format('Y-m-d')}",
                'per_page' => 100,
                'sort' => 'committer-date',
                'order' => 'desc',
            ]);

        if (! $response->successful()) {
            Log::warning("GitHub commit search error for {$username}", [
                'status' => $response->status(),
            ]);

            return [];
        }

        return $response->json()['items'] ?? [];
    }

    /**
     * Aggregate activity data from events and commits.
     */
    private function aggregateActivity(array $events, array $commits, Carbon $since): array
    {
        $stats = [
            'commits' => 0,
            'pull_requests_opened' => 0,
            'pull_requests_merged' => 0,
            'lines_added' => 0,
            'lines_removed' => 0,
            'repos_active' => [],
            'push_events' => 0,
            'period_start' => $since->toDateTimeString(),
            'period_end' => now()->toDateTimeString(),
            'fetched_at' => now()->toDateTimeString(),
        ];

        // Process events
        foreach ($events as $event) {
            $createdAt = Carbon::parse($event['created_at']);

            if ($createdAt->lt($since)) {
                continue;
            }

            $repo = $event['repo']['name'] ?? null;
            if ($repo) {
                $stats['repos_active'][$repo] = true;
            }

            switch ($event['type']) {
                case 'PushEvent':
                    $stats['push_events']++;
                    $commitCount = $event['payload']['size'] ?? 0;
                    $stats['commits'] += $commitCount;
                    break;

                case 'PullRequestEvent':
                    $action = $event['payload']['action'] ?? '';
                    if ($action === 'opened') {
                        $stats['pull_requests_opened']++;
                    } elseif ($action === 'closed' && ($event['payload']['pull_request']['merged'] ?? false)) {
                        $stats['pull_requests_merged']++;

                        // Get line changes from merged PRs
                        $stats['lines_added'] += $event['payload']['pull_request']['additions'] ?? 0;
                        $stats['lines_removed'] += $event['payload']['pull_request']['deletions'] ?? 0;
                    }
                    break;
            }
        }

        // Supplement with commit search data (more accurate commit count)
        $commitCount = count($commits);
        if ($commitCount > $stats['commits']) {
            $stats['commits'] = $commitCount;
        }

        // Convert repos to count
        $stats['repos_active'] = count($stats['repos_active']);

        return $stats;
    }

    /**
     * Compare GitHub activity with claimed Claude Code metrics.
     */
    public function calculateVerificationScore(User $user, array $githubStats, string $period = 'week'): array
    {
        // Get the user's claimed metrics for the same period
        $leaderboardService = app(LeaderboardService::class);
        $claimedStats = $leaderboardService->getUserStats($user, $period);

        $verification = [
            'score' => 0,
            'is_verified' => false,
            'checks' => [],
            'github_stats' => $githubStats,
            'claimed_stats' => $claimedStats,
        ];

        // If no claimed activity, can't verify
        if ($claimedStats['total_tokens'] === 0) {
            $verification['checks']['no_activity'] = 'No Claude Code activity to verify';

            return $verification;
        }

        // If no GitHub activity, suspicious but not conclusive (could be private repos)
        if ($githubStats['commits'] === 0 && $githubStats['push_events'] === 0) {
            $verification['checks']['no_github'] = 'No public GitHub activity found';
            $verification['score'] = 0;

            return $verification;
        }

        $score = 0;
        $maxScore = 0;

        // Check 1: Commits (weight: 40)
        $maxScore += 40;
        if ($claimedStats['commits'] > 0) {
            $commitRatio = min($githubStats['commits'] / $claimedStats['commits'], 1.5);
            $commitScore = min(40, $commitRatio * 40);
            $score += $commitScore;
            $verification['checks']['commits'] = [
                'claimed' => $claimedStats['commits'],
                'github' => $githubStats['commits'],
                'ratio' => round($commitRatio, 2),
                'score' => round($commitScore),
            ];
        } else {
            $verification['checks']['commits'] = ['skipped' => 'No commits claimed'];
        }

        // Check 2: Pull Requests (weight: 30)
        $maxScore += 30;
        if ($claimedStats['pull_requests'] > 0) {
            $prRatio = min(($githubStats['pull_requests_opened'] + $githubStats['pull_requests_merged']) / $claimedStats['pull_requests'], 1.5);
            $prScore = min(30, $prRatio * 30);
            $score += $prScore;
            $verification['checks']['pull_requests'] = [
                'claimed' => $claimedStats['pull_requests'],
                'github' => $githubStats['pull_requests_opened'] + $githubStats['pull_requests_merged'],
                'ratio' => round($prRatio, 2),
                'score' => round($prScore),
            ];
        } else {
            // If they claim tokens but no PRs, that's fine
            $verification['checks']['pull_requests'] = ['skipped' => 'No PRs claimed'];
            $maxScore -= 30; // Don't penalize for no PRs
        }

        // Check 3: Lines of code (weight: 30) - only from merged PRs, so will undercount
        $maxScore += 30;
        $claimedLines = $claimedStats['lines_added'] + $claimedStats['lines_removed'];
        $githubLines = $githubStats['lines_added'] + $githubStats['lines_removed'];

        if ($claimedLines > 0 && $githubLines > 0) {
            // GitHub will undercount since we only see merged PRs, not direct pushes
            // So we're lenient here - even 10% match is good
            $linesRatio = min($githubLines / $claimedLines, 1.5);
            $linesScore = min(30, $linesRatio * 30);
            $score += $linesScore;
            $verification['checks']['lines'] = [
                'claimed' => $claimedLines,
                'github' => $githubLines,
                'ratio' => round($linesRatio, 2),
                'score' => round($linesScore),
            ];
        } else {
            $verification['checks']['lines'] = ['skipped' => 'Insufficient line data'];
            $maxScore -= 30;
        }

        // Calculate final score (0-100)
        $verification['score'] = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;

        // Verified if score >= 50% (lenient because we only see public activity)
        $verification['is_verified'] = $verification['score'] >= 50;

        return $verification;
    }

    /**
     * Get headers for GitHub API requests.
     */
    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Burnboard/1.0',
        ];

        // Use GitHub token if available for higher rate limits
        $token = config('services.github.token');
        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
    }

    /**
     * Return empty stats structure.
     */
    private function emptyStats(): array
    {
        return [
            'commits' => 0,
            'pull_requests_opened' => 0,
            'pull_requests_merged' => 0,
            'lines_added' => 0,
            'lines_removed' => 0,
            'repos_active' => 0,
            'push_events' => 0,
            'period_start' => null,
            'period_end' => null,
            'fetched_at' => null,
        ];
    }
}
