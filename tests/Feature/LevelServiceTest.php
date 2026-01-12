<?php

use App\Models\User;
use App\Models\UserLevel;
use App\Services\LevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->levelService = app(LevelService::class);
});

it('creates initial level for new user', function () {
    $user = User::factory()->create();

    $level = $this->levelService->getOrCreateLevel($user);

    expect($level)->toBeInstanceOf(UserLevel::class)
        ->and($level->tier)->toBe(1)
        ->and($level->level)->toBe(1)
        ->and($level->total_tokens)->toBe(0)
        ->and($level->tier_tokens)->toBe(0);
});

it('adds tokens and updates total', function () {
    $user = User::factory()->create();

    $result = $this->levelService->addTokens($user, 50_000);

    expect($result['current']['total_tokens'])->toBe(50_000)
        ->and($result['current']['tier'])->toBe(1)
        ->and($result['current']['level'])->toBe(1);
});

it('levels up when threshold is reached', function () {
    $user = User::factory()->create();

    // Add tokens to reach level 2 (250K)
    $result = $this->levelService->addTokens($user, 250_000);

    expect($result['level_ups'])->toHaveCount(1)
        ->and($result['level_ups'][0]['level'])->toBe(2)
        ->and($result['current']['level'])->toBe(2);
});

it('jumps to correct level with large token addition', function () {
    $user = User::factory()->create();

    // Add enough tokens to jump from level 1 to level 4 (1M)
    $result = $this->levelService->addTokens($user, 1_000_000);

    // Records one level up event (to the final level)
    expect($result['level_ups'])->toHaveCount(1)
        ->and($result['level_ups'][0]['level'])->toBe(4)
        ->and($result['current']['level'])->toBe(4);
});

it('tiers up when reaching level 10 with enough tokens', function () {
    $user = User::factory()->create();

    // Add tokens to reach tier-up threshold (250M)
    $result = $this->levelService->addTokens($user, 250_000_000);

    expect($result['tier_ups'])->toHaveCount(1)
        ->and($result['tier_ups'][0]['tier'])->toBe(2)
        ->and($result['current']['tier'])->toBe(2)
        ->and($result['current']['tier_name'])->toBe('Ember');
});

it('resets level to 1 after tier up', function () {
    $user = User::factory()->create();

    // Add tokens to reach tier-up threshold (250M)
    $result = $this->levelService->addTokens($user, 250_000_000);

    expect($result['current']['level'])->toBe(1)
        ->and($result['current']['tier'])->toBe(2);
});

it('continues leveling after tier up with excess tokens', function () {
    $user = User::factory()->create();

    // Add tokens for tier up + level 2 worth of tokens (250M + 250K)
    $result = $this->levelService->addTokens($user, 250_250_000);

    expect($result['current']['tier'])->toBe(2)
        ->and($result['current']['level'])->toBe(2);
});

it('handles multiple tier ups at once', function () {
    $user = User::factory()->create();

    // Add tokens for 2 full tiers (500M)
    $result = $this->levelService->addTokens($user, 500_000_000);

    expect($result['tier_ups'])->toHaveCount(2)
        ->and($result['current']['tier'])->toBe(3)
        ->and($result['current']['tier_name'])->toBe('Flame');
});

it('recalculates level from total tokens', function () {
    $user = User::factory()->create();

    // Recalculate with 5M tokens (should be tier 1, level 6)
    $level = $this->levelService->recalculateLevel($user, 5_000_000);

    expect($level->tier)->toBe(1)
        ->and($level->level)->toBe(6)
        ->and($level->total_tokens)->toBe(5_000_000)
        ->and($level->tier_tokens)->toBe(5_000_000);
});

it('recalculates level with tier calculation', function () {
    $user = User::factory()->create();

    // Recalculate with 260M tokens (should be tier 2 with 10M tier_tokens)
    $level = $this->levelService->recalculateLevel($user, 260_000_000);

    expect($level->tier)->toBe(2)
        ->and($level->tier_tokens)->toBe(10_000_000)
        ->and($level->level)->toBe(7); // 10M = level 7
});

it('returns correct tier name for high tiers', function () {
    $user = User::factory()->create();

    // Create level at tier 11 (beyond named tiers)
    $level = UserLevel::create([
        'user_id' => $user->id,
        'tier' => 11,
        'level' => 5,
        'total_tokens' => 2_500_000_000,
        'tier_tokens' => 50_000,
    ]);

    expect($level->tier_name)->toBe('Transcendent II');
});

it('returns correct display name', function () {
    $user = User::factory()->create();

    $this->levelService->addTokens($user, 1_000_000);
    $user->refresh();

    expect($user->level->display_name)->toBe('Spark • Level 4');
});

it('calculates progress to next level correctly', function () {
    $user = User::factory()->create();

    // Add 375K tokens (halfway between level 2 and level 3)
    // Level 2 = 250K, Level 3 = 500K
    $this->levelService->addTokens($user, 375_000);
    $user->refresh();

    expect($user->level->progress_to_next_level)->toBe(50.0);
});

it('calculates tokens to next level correctly', function () {
    $user = User::factory()->create();

    // Add 300K tokens (50K past level 2 threshold)
    // Level 2 = 250K, Level 3 = 500K
    $this->levelService->addTokens($user, 300_000);
    $user->refresh();

    // 500K - 300K = 200K needed for level 3
    expect($user->level->tokens_to_next_level)->toBe(200_000);
});
