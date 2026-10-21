<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\MetricDailyTotal;
use App\Models\MetricTotal;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the metric_totals and metric_daily_totals rollups in step with the
 * metrics table, so request paths never have to aggregate raw metrics.
 */
class MetricRollupService
{
    private const UPSERT_BATCH_SIZE = 500;

    /**
     * Insert raw metric rows and add them to the rollups in one transaction.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function recordMetrics(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        DB::transaction(function () use ($rows) {
            Metric::insert($rows);
            $this->rollUp($rows);
        }, 3);
    }

    /**
     * Add metric rows (arrays or database records) to the rollups.
     * Callers wrap this in a transaction together with whatever they record.
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     */
    public function rollUp(iterable $rows): void
    {
        [$totals, $dailyTotals] = $this->accumulate($rows);

        $this->increment(MetricTotal::class, $totals, ['user_id', 'metric_type']);
        $this->increment(MetricDailyTotal::class, $dailyTotals, ['user_id', 'date', 'metric_type', 'model']);
    }

    /**
     * Sum rows into all-time and daily buckets, keyed and sorted so concurrent
     * writers lock rollup rows in the same order.
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function accumulate(iterable $rows): array
    {
        $totals = [];
        $dailyTotals = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $userId = (int) $row['user_id'];
            $metricType = (string) $row['metric_type'];
            $value = (float) $row['value'];
            $model = (string) ($row['model'] ?? '');
            $recordedAt = $row['recorded_at'] instanceof Carbon
                ? $row['recorded_at']
                : Carbon::parse($row['recorded_at']);
            $date = $recordedAt->toDateString();

            $this->addTo($totals, ['user_id' => $userId, 'metric_type' => $metricType], $value);
            $this->addTo($dailyTotals, ['user_id' => $userId, 'date' => $date, 'metric_type' => $metricType, 'model' => $model], $value);

            if ($metricType === Metric::TYPE_SESSIONS) {
                foreach ($this->sessionBuckets($recordedAt) as $bucket) {
                    $this->addTo($totals, ['user_id' => $userId, 'metric_type' => $bucket], (int) $value);
                }
            }
        }

        ksort($totals);
        ksort($dailyTotals);

        return [$totals, $dailyTotals];
    }

    /**
     * The time-of-day buckets a session counts toward (night 10pm-4am, early 5am-7am, weekend).
     *
     * @return array<int, string>
     */
    private function sessionBuckets(Carbon $recordedAt): array
    {
        $buckets = [];
        $hour = $recordedAt->hour;

        if ($hour >= 22 || $hour <= 4) {
            $buckets[] = MetricTotal::TYPE_NIGHT_SESSIONS;
        }

        if ($hour >= 5 && $hour <= 7) {
            $buckets[] = MetricTotal::TYPE_EARLY_SESSIONS;
        }

        if ($recordedAt->isWeekend()) {
            $buckets[] = MetricTotal::TYPE_WEEKEND_SESSIONS;
        }

        return $buckets;
    }

    /**
     * @param  array<string, array<string, mixed>>  $buckets
     * @param  array<string, mixed>  $key
     */
    private function addTo(array &$buckets, array $key, float|int $value): void
    {
        $id = implode("\0", $key);

        $buckets[$id] ??= [...$key, 'total' => 0];
        $buckets[$id]['total'] += $value;
    }

    /**
     * Upsert rollup rows, adding to the stored total when the row already exists.
     *
     * @param  class-string<MetricTotal|MetricDailyTotal>  $modelClass
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<int, string>  $uniqueBy
     */
    private function increment(string $modelClass, array $rows, array $uniqueBy): void
    {
        foreach (array_chunk($rows, self::UPSERT_BATCH_SIZE) as $batch) {
            $batch = array_map(fn (array $row) => [...$row, 'total' => round($row['total'], 6)], $batch);

            $modelClass::query()->upsert($batch, $uniqueBy, ['total' => $this->addIncomingTotal()]);
        }
    }

    private function addIncomingTotal(): Expression
    {
        $incoming = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
            ? 'values(`total`)'
            : 'excluded.total';

        return DB::raw("total + {$incoming}");
    }
}
