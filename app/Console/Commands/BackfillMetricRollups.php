<?php

namespace App\Console\Commands;

use App\Models\Metric;
use App\Models\MetricRollupBackfill;
use App\Services\MetricRollupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMetricRollups extends Command
{
    protected $signature = 'metrics:backfill-rollups
                            {--chunk=5000 : Metrics rows (by id) to sum per batch}
                            {--sleep=0 : Milliseconds to pause between batches}';

    protected $description = 'Sum metrics recorded before rollups existed into the rollup tables (resumable, safe to re-run)';

    public function __construct(
        private MetricRollupService $metricRollupService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk');
        $sleepMs = (int) $this->option('sleep');

        if ($chunk < 1) {
            $this->error('--chunk must be at least 1.');

            return self::FAILURE;
        }

        $state = MetricRollupBackfill::query()->find(MetricRollupBackfill::METRICS);

        if (! $state) {
            $this->error('No backfill state found. Run the migrations first.');

            return self::FAILURE;
        }

        if ($state->completed_at) {
            $this->info("Backfill already completed at {$state->completed_at} (up to metric id {$state->cutoff_id}).");

            return self::SUCCESS;
        }

        $this->info("Backfilling metric ids {$state->last_id} to {$state->cutoff_id} in batches of {$chunk}...");

        $bar = $this->output->createProgressBar(max($state->cutoff_id - $state->last_id, 0));

        while (($processed = $this->processNextBatch($chunk)) !== null) {
            $bar->advance($processed);

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine();

        MetricRollupBackfill::query()
            ->whereKey(MetricRollupBackfill::METRICS)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    /**
     * Sum the next id range into the rollups and advance the cursor in the same
     * transaction, so a batch is counted exactly once even if the run is
     * interrupted or two runs overlap. Returns the ids covered, or null when done.
     */
    private function processNextBatch(int $chunk): ?int
    {
        return DB::transaction(function () use ($chunk): ?int {
            $state = MetricRollupBackfill::query()
                ->lockForUpdate()
                ->findOrFail(MetricRollupBackfill::METRICS);

            if ($state->last_id >= $state->cutoff_id) {
                return null;
            }

            $upperId = min($state->last_id + $chunk, $state->cutoff_id);

            $rows = Metric::query()
                ->toBase()
                ->select(['user_id', 'metric_type', 'value', 'model', 'recorded_at'])
                ->where('id', '>', $state->last_id)
                ->where('id', '<=', $upperId)
                ->sharedLock()
                ->get();

            $this->metricRollupService->rollUp($rows);

            $processed = $upperId - $state->last_id;
            $state->update(['last_id' => $upperId]);

            return $processed;
        }, 3);
    }
}
