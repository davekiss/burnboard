<?php

use App\Models\Metric;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(LeaderboardService::class);
});

describe('getLeaderboard', function () {
    it('returns users sorted by total tokens descending', function () {
        $user1 = User::factory()->withGithub()->create();
        $user2 = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user1)->tokensInput(10000)->create();
        Metric::factory()->forUser($user2)->tokensInput(50000)->create();

        $leaderboard = $this->service->getLeaderboard();

        expect($leaderboard)->toHaveCount(2);
        expect($leaderboard[0]['github_username'])->toBe($user2->github_username);
        expect($leaderboard[1]['github_username'])->toBe($user1->github_username);
    });

    it('assigns correct ranks', function () {
        $users = User::factory()->withGithub()->count(3)->create();

        Metric::factory()->forUser($users[0])->tokensInput(30000)->create();
        Metric::factory()->forUser($users[1])->tokensInput(10000)->create();
        Metric::factory()->forUser($users[2])->tokensInput(20000)->create();

        $leaderboard = $this->service->getLeaderboard();

        expect($leaderboard[0]['rank'])->toBe(1);
        expect($leaderboard[1]['rank'])->toBe(2);
        expect($leaderboard[2]['rank'])->toBe(3);
    });

    it('respects limit parameter', function () {
        User::factory()->withGithub()->count(10)->create()->each(function ($user) {
            Metric::factory()->forUser($user)->tokensInput(10000)->create();
        });

        $leaderboard = $this->service->getLeaderboard('week', 5);

        expect($leaderboard)->toHaveCount(5);
    });

    it('filters by week period', function () {
        $user = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user)->tokensInput(10000)->recordedAt(now())->create();
        Metric::factory()->forUser($user)->tokensInput(50000)->recordedAt(now()->subWeeks(2))->create();

        $leaderboard = $this->service->getLeaderboard('week');

        expect($leaderboard[0]['total_tokens'])->toBe(10000);
    });

    it('filters by month period', function () {
        $user = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user)->tokensInput(10000)->recordedAt(now())->create();
        Metric::factory()->forUser($user)->tokensInput(50000)->recordedAt(now()->subMonths(2))->create();

        $leaderboard = $this->service->getLeaderboard('month');

        expect($leaderboard[0]['total_tokens'])->toBe(10000);
    });

    it('includes all data for all period', function () {
        $user = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user)->tokensInput(10000)->recordedAt(now())->create();
        Metric::factory()->forUser($user)->tokensInput(50000)->recordedAt(now()->subYears(2))->create();

        $leaderboard = $this->service->getLeaderboard('all');

        expect($leaderboard[0]['total_tokens'])->toBe(60000);
    });

    it('sums all token types correctly', function () {
        $user = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_INPUT, 'value' => 1000])->create();
        Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_OUTPUT, 'value' => 2000])->create();
        Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_CACHE_READ, 'value' => 3000])->create();
        Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_CACHE_CREATION, 'value' => 4000])->create();

        $leaderboard = $this->service->getLeaderboard();

        expect($leaderboard[0]['total_tokens'])->toBe(10000);
    });

    it('excludes users without github username', function () {
        $userWithGithub = User::factory()->withGithub()->create();
        $userWithoutGithub = User::factory()->create(['github_username' => null]);

        Metric::factory()->forUser($userWithGithub)->tokensInput(10000)->create();
        Metric::factory()->forUser($userWithoutGithub)->tokensInput(50000)->create();

        $leaderboard = $this->service->getLeaderboard();

        expect($leaderboard)->toHaveCount(1);
        expect($leaderboard[0]['github_username'])->toBe($userWithGithub->github_username);
    });
});

describe('getUserStats', function () {
    it('returns all stats for user', function () {
        $user = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user)->tokensInput(10000)->create();
        Metric::factory()->forUser($user)->tokensOutput(5000)->create();
        Metric::factory()->forUser($user)->cost(1.50)->create();
        Metric::factory()->forUser($user)->linesAdded(100)->create();
        Metric::factory()->forUser($user)->linesRemoved(20)->create();
        Metric::factory()->forUser($user)->commits(5)->create();

        $stats = $this->service->getUserStats($user);

        expect($stats['total_tokens'])->toBe(15000);
        expect($stats['input_tokens'])->toBe(10000);
        expect($stats['output_tokens'])->toBe(5000);
        expect($stats['total_cost'])->toBe(1.50);
        expect($stats['lines_added'])->toBe(100);
        expect($stats['lines_removed'])->toBe(20);
        expect($stats['commits'])->toBe(5);
        expect($stats['github_username'])->toBe($user->github_username);
    });

    it('calculates cache efficiency correctly', function () {
        $user = User::factory()->withGithub()->create();

        // 100 input + 400 cache read + 0 cache creation = 500 total input
        // Cache efficiency = 400/500 = 80%
        Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_INPUT, 'value' => 100])->create();
        Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_CACHE_READ, 'value' => 400])->create();

        $stats = $this->service->getUserStats($user);

        expect($stats['cache_efficiency'])->toBe(80.0);
    });

    it('includes user rank', function () {
        $user1 = User::factory()->withGithub()->create();
        $user2 = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user1)->tokensInput(50000)->create();
        Metric::factory()->forUser($user2)->tokensInput(10000)->create();

        $stats = $this->service->getUserStats($user2);

        expect($stats['rank'])->toBe(2);
    });
});

describe('getUserRank', function () {
    it('returns correct rank for user', function () {
        $user1 = User::factory()->withGithub()->create();
        $user2 = User::factory()->withGithub()->create();
        $user3 = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user1)->tokensInput(30000)->create();
        Metric::factory()->forUser($user2)->tokensInput(50000)->create();
        Metric::factory()->forUser($user3)->tokensInput(10000)->create();

        expect($this->service->getUserRank($user2))->toBe(1);
        expect($this->service->getUserRank($user1))->toBe(2);
        expect($this->service->getUserRank($user3))->toBe(3);
    });

    it('returns null for user with no tokens', function () {
        $user = User::factory()->withGithub()->create();

        expect($this->service->getUserRank($user))->toBeNull();
    });
});

describe('getModelBreakdown', function () {
    it('returns breakdown by model', function () {
        $user = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user)->tokensInput(10000)->state(['model' => 'claude-sonnet-4-20250514'])->create();
        Metric::factory()->forUser($user)->tokensOutput(5000)->state(['model' => 'claude-sonnet-4-20250514'])->create();
        Metric::factory()->forUser($user)->cost(0.50)->state(['model' => 'claude-sonnet-4-20250514'])->create();

        Metric::factory()->forUser($user)->tokensInput(2000)->state(['model' => 'claude-haiku-3-5-20241022'])->create();
        Metric::factory()->forUser($user)->cost(0.05)->state(['model' => 'claude-haiku-3-5-20241022'])->create();

        $breakdown = $this->service->getModelBreakdown($user);

        expect($breakdown)->toHaveCount(2);
        // Sorted by cost descending
        expect($breakdown[0]->model)->toBe('claude-sonnet-4-20250514');
        expect((float) $breakdown[0]->cost)->toBe(0.5);
        expect($breakdown[1]->model)->toBe('claude-haiku-3-5-20241022');
    });

    it('filters by period', function () {
        $user = User::factory()->withGithub()->create();

        Metric::factory()->forUser($user)->tokensInput(10000)->state(['model' => 'claude-sonnet-4-20250514'])->recordedAt(now())->create();
        Metric::factory()->forUser($user)->tokensInput(50000)->state(['model' => 'claude-opus-4-20250514'])->recordedAt(now()->subMonth())->create();

        $breakdown = $this->service->getModelBreakdown($user, 'week');

        expect($breakdown)->toHaveCount(1);
        expect($breakdown[0]->model)->toBe('claude-sonnet-4-20250514');
    });
});
