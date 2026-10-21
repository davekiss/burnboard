<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only creates new tables; the metrics table is not altered. The backfill
     * cutoff is the highest metrics id before the rollup tables exist: older rows
     * are summed by `metrics:backfill-rollups`, newer rows are rolled up as they
     * are ingested (ingest cannot commit until the rollup tables exist).
     */
    public function up(): void
    {
        $cutoffId = (int) DB::table('metrics')->max('id');

        Schema::create('metric_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric_type');
            $table->decimal('total', 24, 6)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'metric_type']);
        });

        Schema::create('metric_daily_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('metric_type');
            $table->string('model')->default('');
            $table->decimal('total', 24, 6)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date', 'metric_type', 'model']);
            $table->index('date');
        });

        Schema::create('metric_rollup_backfills', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('cutoff_id');
            $table->unsignedBigInteger('last_id')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        DB::table('metric_rollup_backfills')->insert([
            'name' => 'metrics',
            'cutoff_id' => $cutoffId,
            'last_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_rollup_backfills');
        Schema::dropIfExists('metric_daily_totals');
        Schema::dropIfExists('metric_totals');
    }
};
