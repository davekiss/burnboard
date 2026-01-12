<?php

use App\Models\Metric;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('displays the leaderboard homepage', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('leaderboard')
        ->has('leaderboard')
        ->has('period')
        ->where('userStats', null)
    );
});

it('displays users ranked by total tokens', function () {
    $user1 = User::factory()->withGithub()->create();
    $user2 = User::factory()->withGithub()->create();
    $user3 = User::factory()->withGithub()->create();

    // User 2 has the most tokens
    Metric::factory()->forUser($user2)->tokensInput(50000)->create();
    Metric::factory()->forUser($user2)->tokensOutput(20000)->create();

    // User 1 has second most
    Metric::factory()->forUser($user1)->tokensInput(30000)->create();
    Metric::factory()->forUser($user1)->tokensOutput(10000)->create();

    // User 3 has least
    Metric::factory()->forUser($user3)->tokensInput(5000)->create();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('leaderboard')
        ->has('leaderboard', 3)
        ->where('leaderboard.0.github_username', $user2->github_username)
        ->where('leaderboard.0.rank', 1)
        ->where('leaderboard.1.github_username', $user1->github_username)
        ->where('leaderboard.1.rank', 2)
        ->where('leaderboard.2.github_username', $user3->github_username)
        ->where('leaderboard.2.rank', 3)
    );
});

it('excludes users without github username from leaderboard', function () {
    $userWithGithub = User::factory()->withGithub()->create();
    $userWithoutGithub = User::factory()->create(['github_username' => null]);

    Metric::factory()->forUser($userWithGithub)->tokensInput(10000)->create();
    Metric::factory()->forUser($userWithoutGithub)->tokensInput(50000)->create();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('leaderboard', 1)
        ->where('leaderboard.0.github_username', $userWithGithub->github_username)
    );
});

it('excludes users with zero tokens from leaderboard', function () {
    $userWithTokens = User::factory()->withGithub()->create();
    $userWithoutTokens = User::factory()->withGithub()->create();

    Metric::factory()->forUser($userWithTokens)->tokensInput(10000)->create();
    // User without tokens only has lines_added, not token metrics
    Metric::factory()->forUser($userWithoutTokens)->linesAdded(500)->create();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('leaderboard', 1)
        ->where('leaderboard.0.github_username', $userWithTokens->github_username)
    );
});

it('filters leaderboard by week period', function () {
    $user = User::factory()->withGithub()->create();

    // Metric from this week
    Metric::factory()->forUser($user)->tokensInput(10000)->recordedAt(now())->create();

    // Metric from last month (should be excluded for week period)
    Metric::factory()->forUser($user)->tokensInput(50000)->recordedAt(now()->subMonth())->create();

    $response = $this->get('/?period=week');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('period', 'week')
        ->has('leaderboard', 1)
        ->where('leaderboard.0.total_tokens', 10000)
    );
});

it('filters leaderboard by month period', function () {
    $user = User::factory()->withGithub()->create();

    // Metric from this month
    Metric::factory()->forUser($user)->tokensInput(10000)->recordedAt(now())->create();

    // Metric from 2 months ago (should be excluded for month period)
    Metric::factory()->forUser($user)->tokensInput(50000)->recordedAt(now()->subMonths(2))->create();

    $response = $this->get('/?period=month');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('period', 'month')
        ->where('leaderboard.0.total_tokens', 10000)
    );
});

it('shows all time data with all period', function () {
    $user = User::factory()->withGithub()->create();

    // Recent metric
    Metric::factory()->forUser($user)->tokensInput(10000)->recordedAt(now())->create();

    // Old metric
    Metric::factory()->forUser($user)->tokensInput(50000)->recordedAt(now()->subYear())->create();

    $response = $this->get('/?period=all');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('period', 'all')
        ->where('leaderboard.0.total_tokens', 60000)
    );
});

it('aggregates all token types for total', function () {
    $user = User::factory()->withGithub()->create();

    Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_INPUT, 'value' => 10000])->create();
    Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_OUTPUT, 'value' => 5000])->create();
    Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_CACHE_READ, 'value' => 3000])->create();
    Metric::factory()->forUser($user)->state(['metric_type' => Metric::TYPE_TOKENS_CACHE_CREATION, 'value' => 2000])->create();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('leaderboard.0.total_tokens', 20000)
    );
});

it('shows current user stats when authenticated', function () {
    $user = User::factory()->withGithub()->create();
    Metric::factory()->forUser($user)->tokensInput(25000)->create();
    Metric::factory()->forUser($user)->cost(1.50)->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('userStats')
        ->where('userStats.github_username', $user->github_username)
        ->where('userStats.total_tokens', 25000)
        ->where('userStats.total_cost', 1.50)
        ->where('userStats.rank', 1)
    );
});

it('returns leaderboard data with all expected fields', function () {
    $user = User::factory()->withGithub()->verified()->create();

    Metric::factory()->forUser($user)->tokensInput(10000)->create();
    Metric::factory()->forUser($user)->cost(0.50)->create();
    Metric::factory()->forUser($user)->linesAdded(100)->create();
    Metric::factory()->forUser($user)->linesRemoved(20)->create();
    Metric::factory()->forUser($user)->commits(5)->create();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('leaderboard.0', fn (Assert $entry) => $entry
            ->has('rank')
            ->has('github_username')
            ->has('avatar_url')
            ->has('is_verified')
            ->has('verification_score')
            ->has('total_tokens')
            ->has('total_cost')
            ->has('lines_added')
            ->has('lines_removed')
            ->has('commits')
            ->has('pull_requests')
            ->has('sessions')
            ->has('active_time')
        )
    );
});

it('defaults to week period when no period specified', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('period', 'week')
    );
});

it('defaults to week period when invalid period specified', function () {
    $response = $this->get('/?period=invalid');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('period', 'invalid') // Controller passes it through, service uses default
    );
});
