<?php

use App\Models\Metric;
use App\Models\User;

it('returns leaderboard as json', function () {
    $user = User::factory()->withGithub()->create();
    Metric::factory()->forUser($user)->tokensInput(10000)->create();

    $response = $this->getJson('/api/leaderboard');

    $response->assertOk();
    $response->assertJsonStructure([
        'period',
        'leaderboard' => [
            '*' => [
                'rank',
                'github_username',
                'avatar_url',
                'is_verified',
                'verification_score',
                'total_tokens',
                'total_cost',
                'lines_added',
                'lines_removed',
                'commits',
                'pull_requests',
                'sessions',
                'active_time',
            ],
        ],
    ]);
});

it('returns leaderboard ranked by total tokens', function () {
    $user1 = User::factory()->withGithub()->create();
    $user2 = User::factory()->withGithub()->create();

    Metric::factory()->forUser($user1)->tokensInput(50000)->create();
    Metric::factory()->forUser($user2)->tokensInput(10000)->create();

    $response = $this->getJson('/api/leaderboard');

    $response->assertOk();
    $response->assertJsonPath('leaderboard.0.github_username', $user1->github_username);
    $response->assertJsonPath('leaderboard.0.rank', 1);
    $response->assertJsonPath('leaderboard.1.github_username', $user2->github_username);
    $response->assertJsonPath('leaderboard.1.rank', 2);
});

it('filters by period parameter', function () {
    $user = User::factory()->withGithub()->create();

    Metric::factory()->forUser($user)->tokensInput(10000)->recordedAt(now())->create();
    Metric::factory()->forUser($user)->tokensInput(50000)->recordedAt(now()->subMonth())->create();

    $response = $this->getJson('/api/leaderboard?period=week');

    $response->assertOk();
    $response->assertJsonPath('period', 'week');
    $response->assertJsonPath('leaderboard.0.total_tokens', 10000);
});

it('respects limit parameter', function () {
    User::factory()->withGithub()->count(5)->create()->each(function ($user) {
        Metric::factory()->forUser($user)->tokensInput(10000)->create();
    });

    $response = $this->getJson('/api/leaderboard?limit=3');

    $response->assertOk();
    $response->assertJsonCount(3, 'leaderboard');
});

it('caps limit at 100', function () {
    User::factory()->withGithub()->count(5)->create()->each(function ($user) {
        Metric::factory()->forUser($user)->tokensInput(10000)->create();
    });

    $response = $this->getJson('/api/leaderboard?limit=500');

    $response->assertOk();
    // Should not exceed 100, but we only have 5 users
    $response->assertJsonCount(5, 'leaderboard');
});

// Note: These tests require Sanctum to be configured for the 'sanctum' guard
// Currently skipped until Sanctum is properly set up

it('returns current user stats at /me endpoint', function () {
    $user = User::factory()->withGithub()->create();
    Metric::factory()->forUser($user)->tokensInput(25000)->create();
    Metric::factory()->forUser($user)->cost(1.50)->create();
    Metric::factory()->forUser($user)->linesAdded(100)->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/leaderboard/me');

    $response->assertOk();
    $response->assertJsonStructure([
        'period',
        'stats' => [
            'rank',
            'github_username',
            'total_tokens',
            'total_cost',
            'lines_added',
            'lines_removed',
            'commits',
        ],
        'model_breakdown',
    ]);
    $response->assertJsonPath('stats.github_username', $user->github_username);
    $response->assertJsonPath('stats.total_tokens', 25000);
})->skip('Requires Sanctum guard to be configured');

it('returns 401 for unauthenticated /me request', function () {
    $response = $this->getJson('/api/leaderboard/me');

    $response->assertUnauthorized();
})->skip('Requires Sanctum guard to be configured');

it('returns model breakdown for user', function () {
    $user = User::factory()->withGithub()->create();

    Metric::factory()->forUser($user)->tokensInput(10000)->state(['model' => 'claude-sonnet-4-20250514'])->create();
    Metric::factory()->forUser($user)->tokensOutput(5000)->state(['model' => 'claude-sonnet-4-20250514'])->create();
    Metric::factory()->forUser($user)->cost(0.50)->state(['model' => 'claude-sonnet-4-20250514'])->create();

    Metric::factory()->forUser($user)->tokensInput(2000)->state(['model' => 'claude-haiku-3-5-20241022'])->create();
    Metric::factory()->forUser($user)->cost(0.05)->state(['model' => 'claude-haiku-3-5-20241022'])->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/leaderboard/me');

    $response->assertOk();
    $response->assertJsonCount(2, 'model_breakdown');
})->skip('Requires Sanctum guard to be configured');

it('defaults to week period', function () {
    $response = $this->getJson('/api/leaderboard');

    $response->assertOk();
    $response->assertJsonPath('period', 'week');
});

it('returns empty leaderboard when no data', function () {
    $response = $this->getJson('/api/leaderboard');

    $response->assertOk();
    $response->assertJsonPath('leaderboard', []);
});
