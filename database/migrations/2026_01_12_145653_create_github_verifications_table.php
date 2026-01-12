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
        Schema::create('github_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // GitHub activity stats
            $table->unsignedInteger('github_commits')->default(0);
            $table->unsignedInteger('github_prs_opened')->default(0);
            $table->unsignedInteger('github_prs_merged')->default(0);
            $table->unsignedInteger('github_lines_added')->default(0);
            $table->unsignedInteger('github_lines_removed')->default(0);
            $table->unsignedInteger('github_repos_active')->default(0);
            $table->unsignedInteger('github_push_events')->default(0);

            // Verification result
            $table->unsignedTinyInteger('verification_score')->default(0); // 0-100
            $table->boolean('is_verified')->default(false);
            $table->json('verification_checks')->nullable(); // Detailed breakdown

            // Period this verification covers
            $table->string('period')->default('week'); // day, week, month, all
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();

            // When we fetched from GitHub
            $table->timestamp('fetched_at')->nullable();

            $table->timestamps();

            // Index for quick lookups
            $table->index(['user_id', 'period', 'fetched_at']);
        });

        // Add is_verified column to users for quick access
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('api_token');
            $table->unsignedTinyInteger('verification_score')->default(0)->after('is_verified');
            $table->timestamp('verified_at')->nullable()->after('verification_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_verified', 'verification_score', 'verified_at']);
        });

        Schema::dropIfExists('github_verifications');
    }
};
