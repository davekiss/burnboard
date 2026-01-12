<?php

use App\Models\Badge;
use App\Models\LeaderboardPosition;
use App\Models\Metric;
use App\Models\User;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->badgeService = app(BadgeService::class);
    $this->seed(BadgeSeeder::class);
});

it('awards token milestone badge when threshold is met', function () {
    $user = User::factory()->create();

    // Add metrics for 100K tokens (threshold for Token Taster badge)
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_TOKENS_INPUT,
        'value' => 100_000,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    expect($awarded)->not->toBeEmpty();

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('tokens-100k');
});

it('awards multiple badges at once', function () {
    $user = User::factory()->create();

    // Add metrics for 1M tokens (should earn 100K, 250K, 500K, and 1M badges)
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_TOKENS_INPUT,
        'value' => 1_000_000,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('tokens-100k')
        ->and($slugs)->toContain('tokens-250k')
        ->and($slugs)->toContain('tokens-500k')
        ->and($slugs)->toContain('tokens-1m');
});

it('does not award same badge twice', function () {
    $user = User::factory()->create();

    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_TOKENS_INPUT,
        'value' => 100_000,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    // First check should award badge
    $firstCheck = $this->badgeService->checkAndAwardBadges($user);
    expect($firstCheck)->not->toBeEmpty();

    // Second check should not award same badge
    $secondCheck = $this->badgeService->checkAndAwardBadges($user);
    $slugs = collect($secondCheck)->pluck('badge.slug')->toArray();
    expect($slugs)->not->toContain('tokens-100k');
});

it('awards cost milestone badge', function () {
    $user = User::factory()->create();

    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_COST,
        'value' => 10.00,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('cost-1')
        ->and($slugs)->toContain('cost-10');
});

it('awards streak badge for consecutive days', function () {
    $user = User::factory()->create();

    // Create metrics for 3 consecutive days
    for ($i = 0; $i < 3; $i++) {
        Metric::create([
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_SESSIONS,
            'value' => 1,
            'source' => Metric::SOURCE_CLAUDE_CODE,
            'recorded_at' => now()->subDays($i),
        ]);
    }

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('streak-3');
});

it('does not award streak badge for non-consecutive days', function () {
    $user = User::factory()->create();

    // Create metrics with a gap (days 0, 1, 3 - missing day 2)
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_SESSIONS,
        'value' => 1,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_SESSIONS,
        'value' => 1,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now()->subDays(1),
    ]);
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_SESSIONS,
        'value' => 1,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now()->subDays(3),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->not->toContain('streak-3');
});

it('awards leaderboard champion badge', function () {
    $user = User::factory()->create();

    // Create leaderboard position record for weekly #1
    LeaderboardPosition::create([
        'user_id' => $user->id,
        'period' => LeaderboardPosition::PERIOD_WEEK,
        'position' => 1,
        'achieved_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('weekly-champion');
});

it('awards hold time badge when duration threshold is met', function () {
    $user = User::factory()->create();

    // Create leaderboard position with 2 hours hold time
    LeaderboardPosition::create([
        'user_id' => $user->id,
        'period' => LeaderboardPosition::PERIOD_ALL,
        'position' => 1,
        'achieved_at' => now()->subHours(2),
        'lost_at' => now(),
        'duration_ms' => 7_200_000, // 2 hours
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('hold-1h');
});

it('awards commit milestone badge', function () {
    $user = User::factory()->create();

    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_COMMITS,
        'value' => 10,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('commits-10');
});

it('awards PR milestone badge', function () {
    $user = User::factory()->create();

    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_PULL_REQUESTS,
        'value' => 1,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('pr-1');
});

it('awards surgeon badge for net negative lines', function () {
    $user = User::factory()->create();

    // Add 500 lines, remove 1500 lines = -1000 net
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_LINES_ADDED,
        'value' => 500,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_LINES_REMOVED,
        'value' => 1500,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->toContain('surgeon');
});

it('hasBadge returns correct result', function () {
    $user = User::factory()->create();

    expect($this->badgeService->hasBadge($user, 'tokens-100k'))->toBeFalse();

    // Award badge
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_TOKENS_INPUT,
        'value' => 100_000,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);
    $this->badgeService->checkAndAwardBadges($user);

    expect($this->badgeService->hasBadge($user, 'tokens-100k'))->toBeTrue();
});

it('awardBadgeBySlug awards specific badge', function () {
    $user = User::factory()->create();

    $result = $this->badgeService->awardBadgeBySlug($user, 'welcome', ['reason' => 'first login']);

    expect($result)->not->toBeNull()
        ->and($result->badge->slug)->toBe('welcome')
        ->and($result->metadata['reason'])->toBe('first login');
});

it('awardBadgeBySlug returns null for already earned badge', function () {
    $user = User::factory()->create();

    // Award first time
    $this->badgeService->awardBadgeBySlug($user, 'welcome');

    // Try to award again
    $result = $this->badgeService->awardBadgeBySlug($user, 'welcome');

    expect($result)->toBeNull();
});

it('getUserBadgesByCategory organizes badges correctly', function () {
    $user = User::factory()->create();

    // Award badges from different categories
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_TOKENS_INPUT,
        'value' => 100_000,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);
    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_COMMITS,
        'value' => 10,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $this->badgeService->checkAndAwardBadges($user);

    $badgesByCategory = $this->badgeService->getUserBadgesByCategory($user);

    expect($badgesByCategory)->toHaveKey(Badge::CATEGORY_MILESTONE);
    expect($badgesByCategory[Badge::CATEGORY_MILESTONE])->not->toBeEmpty();
});

it('includes metadata when awarding badge', function () {
    $user = User::factory()->create();

    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_TOKENS_INPUT,
        'value' => 100_000,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $tokenBadge = collect($awarded)->firstWhere('badge.slug', 'tokens-100k');
    expect($tokenBadge['metadata'])->toHaveKey('value')
        ->and($tokenBadge['metadata'])->toHaveKey('threshold')
        ->and($tokenBadge['metadata']['threshold'])->toBe(100_000);
});

it('only checks active badges', function () {
    $user = User::factory()->create();

    // Deactivate all token badges
    Badge::where('slug', 'like', 'tokens-%')->update(['is_active' => false]);

    Metric::create([
        'user_id' => $user->id,
        'metric_type' => Metric::TYPE_TOKENS_INPUT,
        'value' => 100_000,
        'source' => Metric::SOURCE_CLAUDE_CODE,
        'recorded_at' => now(),
    ]);

    $awarded = $this->badgeService->checkAndAwardBadges($user);

    $slugs = collect($awarded)->pluck('badge.slug')->toArray();
    expect($slugs)->not->toContain('tokens-100k');
});
