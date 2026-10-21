<?php

use App\Models\Metric;
use App\Models\MetricDailyTotal;
use App\Models\MetricRollupBackfill;
use App\Models\MetricTotal;
use App\Models\User;
use App\Services\MetricRollupService;
use Illuminate\Support\Carbon;

/**
 * Insert raw metrics the way the old ingest did: no rollups. Returns the highest id.
 *
 * @param  array<int, array{0: string, 1: int|float, 2: string|null, 3: string}>  $rows
 */
function insertLegacyMetrics(User $user, array $rows): int
{
    Metric::insert(array_map(fn (array $row) => [
        'user_id' => $user->id,
        'metric_type' => $row[0],
        'value' => $row[1],
        'model' => $row[2],
        'recorded_at' => Carbon::parse($row[3]),
        'created_at' => now(),
        'updated_at' => now(),
    ], $rows));

    return (int) Metric::query()->max('id');
}

function setBackfillCursor(int $cutoffId, int $lastId = 0): void
{
    MetricRollupBackfill::query()->whereKey(MetricRollupBackfill::METRICS)->update([
        'cutoff_id' => $cutoffId,
        'last_id' => $lastId,
        'completed_at' => null,
    ]);
}

/**
 * @return array<string, float>
 */
function rollupTotals(User $user): array
{
    return MetricTotal::query()
        ->where('user_id', $user->id)
        ->orderBy('metric_type')
        ->pluck('total', 'metric_type')
        ->map(fn ($total) => (float) $total)
        ->all();
}

beforeEach(function () {
    $this->user = User::factory()->withGithub()->create();

    $this->cutoffId = insertLegacyMetrics($this->user, [
        [Metric::TYPE_TOKENS_INPUT, 1000, 'claude-sonnet-4', '2026-10-01 10:00:00'],
        [Metric::TYPE_TOKENS_INPUT, 2000, 'claude-sonnet-4', '2026-10-01 11:00:00'],
        [Metric::TYPE_TOKENS_INPUT, 400, null, '2026-10-02 10:00:00'],
        [Metric::TYPE_COST, 0.125, 'claude-sonnet-4', '2026-10-02 10:00:00'],
        [Metric::TYPE_SESSIONS, 1, null, '2026-10-03 23:30:00'],
        [Metric::TYPE_LINES_ADDED, 0, null, '2026-10-04 12:00:00'],
    ]);

    setBackfillCursor($this->cutoffId);
});

it('sums metrics recorded before rollups existed into the rollups', function () {
    $this->artisan('metrics:backfill-rollups', ['--chunk' => 2])->assertSuccessful();

    expect(rollupTotals($this->user))->toBe([
        Metric::TYPE_COST => 0.125,
        Metric::TYPE_LINES_ADDED => 0.0,
        Metric::TYPE_SESSIONS => 1.0,
        MetricTotal::TYPE_NIGHT_SESSIONS => 1.0,
        MetricTotal::TYPE_WEEKEND_SESSIONS => 1.0,
        Metric::TYPE_TOKENS_INPUT => 3400.0,
    ]);

    $sonnetDay = MetricDailyTotal::query()
        ->where('user_id', $this->user->id)
        ->where('date', '2026-10-01')
        ->where('metric_type', Metric::TYPE_TOKENS_INPUT)
        ->where('model', 'claude-sonnet-4')
        ->value('total');

    expect((float) $sonnetDay)->toBe(3000.0)
        ->and($this->user->metricDailyTotals()->distinct()->pluck('date')->map->toDateString()->all())
        ->toEqualCanonicalizing(['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04']);

    $state = MetricRollupBackfill::query()->find(MetricRollupBackfill::METRICS);

    expect($state->last_id)->toBe($this->cutoffId)
        ->and($state->completed_at)->not->toBeNull();
});

it('is idempotent when run again', function () {
    $this->artisan('metrics:backfill-rollups')->assertSuccessful();
    $afterFirstRun = rollupTotals($this->user);
    $dailyRowsAfterFirstRun = MetricDailyTotal::query()->orderBy('id')->get(['date', 'metric_type', 'model', 'total'])->toArray();

    $this->artisan('metrics:backfill-rollups')
        ->expectsOutputToContain('already completed')
        ->assertSuccessful();

    expect(rollupTotals($this->user))->toBe($afterFirstRun)
        ->and(MetricDailyTotal::query()->orderBy('id')->get(['date', 'metric_type', 'model', 'total'])->toArray())
        ->toBe($dailyRowsAfterFirstRun);
});

it('resumes from where an interrupted run stopped', function () {
    $firstId = $this->cutoffId - 5;

    // Simulate a run that committed the batch covering the first two rows, then died.
    app(MetricRollupService::class)->rollUp(
        Metric::query()->toBase()->where('id', '<=', $firstId + 1)->get()
    );
    setBackfillCursor($this->cutoffId, $firstId + 1);

    $this->artisan('metrics:backfill-rollups', ['--chunk' => 1])->assertSuccessful();

    expect(rollupTotals($this->user)[Metric::TYPE_TOKENS_INPUT])->toBe(3400.0);
});

it('does not count rows above the cutoff that were rolled up on ingest', function () {
    Metric::factory()->forUser($this->user)->tokensInput(600)->recordedAt(Carbon::parse('2026-10-05 10:00:00'))->create();

    $this->artisan('metrics:backfill-rollups')->assertSuccessful();

    $rawInputSum = (float) Metric::query()
        ->where('user_id', $this->user->id)
        ->where('metric_type', Metric::TYPE_TOKENS_INPUT)
        ->sum('value');

    expect($rawInputSum)->toBe(4000.0)
        ->and(rollupTotals($this->user)[Metric::TYPE_TOKENS_INPUT])->toBe($rawInputSum);
});

it('rejects a chunk size below one', function () {
    $this->artisan('metrics:backfill-rollups', ['--chunk' => 0])->assertFailed();

    expect(rollupTotals($this->user))->toBe([]);
});
