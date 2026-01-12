<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period'); // 'week', 'month', 'all'
            $table->unsignedInteger('position'); // 1, 2, 3...
            $table->timestamp('achieved_at');
            $table->timestamp('lost_at')->nullable(); // null if still holding
            $table->unsignedBigInteger('duration_ms')->nullable(); // milliseconds held
            $table->timestamps();

            $table->index(['user_id', 'period']);
            $table->index(['period', 'position']);
            $table->index('achieved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_positions');
    }
};
