<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('earned_at');
            $table->json('metadata')->nullable(); // extra context (e.g., "duration_ms": 3600000)
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
