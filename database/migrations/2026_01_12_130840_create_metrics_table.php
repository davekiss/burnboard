<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('metric_type'); // tokens_input, tokens_output, cost, lines_added, lines_removed, commits, pull_requests, sessions
            $table->decimal('value', 20, 6)->default(0);
            $table->string('model')->nullable(); // claude-opus-4-5, claude-sonnet-4, etc.
            $table->string('session_id')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['user_id', 'metric_type', 'recorded_at']);
            $table->index(['recorded_at']);
            $table->index(['metric_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
