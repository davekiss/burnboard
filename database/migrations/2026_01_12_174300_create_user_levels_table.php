<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tier')->default(1); // 1 = Spark, 2 = Ember, etc.
            $table->unsignedTinyInteger('level')->default(1); // 1-10 within tier
            $table->unsignedBigInteger('total_tokens')->default(0); // lifetime tokens
            $table->unsignedBigInteger('tier_tokens')->default(0); // tokens in current tier (resets on tier-up)
            $table->timestamp('last_level_up_at')->nullable();
            $table->timestamp('last_tier_up_at')->nullable();
            $table->timestamps();

            $table->index('tier');
            $table->index('total_tokens');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_levels');
    }
};
