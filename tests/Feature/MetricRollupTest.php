<?php

use App\Models\Metric;
use App\Models\MetricDailyTotal;
use App\Models\MetricTotal;
use App\Models\User;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * An OTLP metrics payload with one data point per [type, value, model] entry.
 *
 * @param  array<int, array{0: string, 1: int|float, 2?: string|null}>  $tokens
 */
function otlpTokenPayload(array $tokens, ?Carbon $at = null): array
{
    $dataPoints = array_map(fn (array $token) => [
        'asInt' => $token[1],
        'timeUnixNano' => (string) (($at ?? now())->getTimestamp() * 1_000_000_000),
        'attributes' => array_values(array_filter([
            ['key' => 'type', 'value' => ['stringValue' => $token[0]]],
            isset($token[2]) ? ['key' => 'model', 'value' => ['stringValue' => $token[2]]] : null,
        ])),
    ], $tokens);

    return [
        'resourceMetrics' => [[
            'scopeMetrics' => [[
                'metrics' => [[
                    'name' => 'claude_code.token.usage',
                    'sum' => ['dataPoints' => $dataPoints],
                ]],
            ]],
        ]],
    ];
}

/**
 * Run a callback and return the SQL of every query that touched the raw metrics table.
 *
 * @return array<int, string>
 */
function queriesOnMetricsTable(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        if (preg_match('/[`"]metrics[`"]/', $query->sql)) {
            $queries[] = $query->sql;
        }
    });

    $callback();

    return $queries;
}

function metricTotal(User $user, string $metricType): float
{
    return (float) MetricTotal::query()
        ->where('user_id', $user->id)
        ->where('metric_type', $metricType)
        ->value('total');
}

describe('ingest', function () {
    it('adds ingested metrics to the all-time and daily rollups', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();
        $headers = ['Authorization' => "Bearer {$user->api_token}"];

        $this->postJson('/api/v1/metrics', otlpTokenPayload([
            ['input', 1000, 'claude-sonnet-4'],
            ['input', 500, 'claude-opus-4'],
            ['output', 200, 'claude-sonnet-4'],
        ]), $headers)->assertOk();

        $this->postJson('/api/v1/metrics', otlpTokenPayload([
            ['input', 250, 'claude-sonnet-4'],
        ]), $headers)->assertOk();

        expect(metricTotal($user, Metric::TYPE_TOKENS_INPUT))->toBe(1750.0)
            ->and(metricTotal($user, Metric::TYPE_TOKENS_OUTPUT))->toBe(200.0);

        $sonnetInput = MetricDailyTotal::query()
            ->where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->where('metric_type', Metric::TYPE_TOKENS_INPUT)
            ->where('model', 'claude-sonnet-4')
            ->value('total');

        expect((float) $sonnetInput)->toBe(1250.0)
            ->and(MetricDailyTotal::query()->where('user_id', $user->id)->count())->toBe(3);
    });

    it('rolls up metrics without a model under an empty model', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $this->postJson('/api/v1/metrics', otlpTokenPayload([['input', 100]]), [
            'Authorization' => "Bearer {$user->api_token}",
        ])->assertOk();

        $this->assertDatabaseHas('metric_daily_totals', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_INPUT,
            'model' => '',
            'total' => 100,
        ]);
    });

    it('rolls up codex logs and opencode traces', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();
        $headers = ['Authorization' => "Bearer {$user->api_token}"];
        $nanos = (string) (now()->getTimestamp() * 1_000_000_000);

        $this->postJson('/api/v1/logs', [
            'resourceLogs' => [['scopeLogs' => [['logRecords' => [[
                'timeUnixNano' => $nanos,
                'attributes' => [
                    ['key' => 'event.name', 'value' => ['stringValue' => 'codex.sse_event']],
                    ['key' => 'input_token_count', 'value' => ['intValue' => 300]],
                ],
            ]]]]]],
        ], $headers)->assertOk();

        $this->postJson('/api/v1/traces', [
            'resourceSpans' => [['scopeSpans' => [['spans' => [[
                'startTimeUnixNano' => $nanos,
                'attributes' => [
                    ['key' => 'ai.usage.promptTokens', 'value' => ['intValue' => 700]],
                ],
            ]]]]]],
        ], $headers)->assertOk();

        expect(metricTotal($user, Metric::TYPE_TOKENS_INPUT))->toBe(1000.0);
    });

    it('never reads the metrics table, only inserts into it', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();
        Metric::factory()->forUser($user)->tokensInput(5000)->create();

        $queries = queriesOnMetricsTable(function () use ($user) {
            $this->postJson('/api/v1/metrics', otlpTokenPayload([['input', 1000, 'claude-sonnet-4']]), [
                'Authorization' => "Bearer {$user->api_token}",
            ])->assertOk();
        });

        expect($queries)->toHaveCount(1)
            ->and($queries[0])->toMatch('/^insert into [`"]metrics[`"]/');
    });

    it('still awards token badges from the rollups', function () {
        $this->seed(BadgeSeeder::class);
        $user = User::factory()->withGithub()->withApiToken()->create();

        $response = $this->postJson('/api/v1/metrics', otlpTokenPayload([['input', 1_000_000]]), [
            'Authorization' => "Bearer {$user->api_token}",
        ])->assertOk();

        expect(collect($response->json('badges_earned'))->pluck('slug'))->toContain('tokens-1m');
    });
});

describe('request paths', function () {
    it('serve leaderboards and profiles without touching the metrics table', function (string $period) {
        $user = User::factory()->withGithub()->create();
        Metric::factory()->forUser($user)->tokensInput(5000)->create();
        Metric::factory()->forUser($user)->cost(1.5)->create();

        $queries = queriesOnMetricsTable(function () use ($user, $period) {
            $this->get("/?period={$period}")->assertOk();
            $this->getJson("/api/leaderboard?period={$period}")->assertOk();
            $this->get("/u/{$user->github_username}?period={$period}")->assertOk();
        });

        expect($queries)->toBe([]);
    })->with(['day', 'week', 'month', 'year', 'all']);
});

describe('badge stats', function () {
    it('counts a day with only zero-value metrics toward the streak', function () {
        $this->seed(BadgeSeeder::class);
        $user = User::factory()->withGithub()->create();

        foreach (range(0, 2) as $daysAgo) {
            Metric::factory()->forUser($user)->tokensInput(0)->recordedAt(now()->subDays($daysAgo))->create();
        }

        app(BadgeService::class)->checkAndAwardBadges($user);

        expect($user->metricDailyTotals()->count())->toBe(3)
            ->and(app(BadgeService::class)->hasBadge($user, 'streak-3'))->toBeTrue();
    });

    it('buckets sessions by night, early morning and weekend', function () {
        $user = User::factory()->withGithub()->create();
        $sessionAt = fn (string $at) => Metric::factory()->forUser($user)
            ->state(['metric_type' => Metric::TYPE_SESSIONS, 'value' => 1])
            ->recordedAt(Carbon::parse($at))
            ->create();

        $sessionAt('2026-10-17 23:00:00'); // Saturday night
        $sessionAt('2026-10-21 06:00:00'); // Wednesday early
        $sessionAt('2026-10-21 03:00:00'); // Wednesday night
        $sessionAt('2026-10-21 14:00:00'); // Wednesday afternoon

        expect(metricTotal($user, Metric::TYPE_SESSIONS))->toBe(4.0)
            ->and(metricTotal($user, MetricTotal::TYPE_NIGHT_SESSIONS))->toBe(2.0)
            ->and(metricTotal($user, MetricTotal::TYPE_EARLY_SESSIONS))->toBe(1.0)
            ->and(metricTotal($user, MetricTotal::TYPE_WEEKEND_SESSIONS))->toBe(1.0);
    });
});

it('clears the rollups when a user deletes their stats', function () {
    $user = User::factory()->withGithub()->create();
    Metric::factory()->forUser($user)->tokensInput(5000)->create();

    $this->actingAs($user)->delete(route('dashboard.stats.delete'))->assertRedirect();

    expect($user->metrics()->count())->toBe(0)
        ->and($user->metricTotals()->count())->toBe(0)
        ->and($user->metricDailyTotals()->count())->toBe(0);
});
