<?php

namespace App\Console\Commands;

use App\Models\GitHubVerification;
use App\Models\User;
use App\Services\GitHubVerificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncGitHubActivity extends Command
{
    protected $signature = 'github:sync
                            {--user= : Sync a specific user by ID or username}
                            {--period=week : Period to verify (day, week, month, all)}
                            {--force : Force sync even if recently synced}';

    protected $description = 'Sync GitHub public activity for users and calculate verification scores';

    public function __construct(
        private GitHubVerificationService $verificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $period = $this->option('period');
        $force = $this->option('force');

        // Determine which users to sync
        if ($userId = $this->option('user')) {
            $users = User::where('id', $userId)
                ->orWhere('github_username', $userId)
                ->get();

            if ($users->isEmpty()) {
                $this->error("User not found: {$userId}");

                return self::FAILURE;
            }
        } else {
            // Get users who have metrics (active users)
            $users = User::whereHas('metrics')
                ->whereNotNull('github_username')
                ->get();
        }

        $this->info("Syncing GitHub activity for {$users->count()} users...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                // Skip if recently synced (within last hour) unless forced
                if (! $force) {
                    $recentVerification = $user->githubVerifications()
                        ->where('period', $period)
                        ->where('fetched_at', '>', now()->subHour())
                        ->first();

                    if ($recentVerification) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }
                }

                // Determine the date range based on period
                $since = match ($period) {
                    'day' => now()->startOfDay(),
                    'week' => now()->startOfWeek(),
                    'month' => now()->startOfMonth(),
                    'all' => now()->subYears(2), // GitHub events only go back ~90 days anyway
                    default => now()->startOfWeek(),
                };

                // Fetch GitHub activity
                $githubStats = $this->verificationService->fetchUserActivity($user, $since);

                // Calculate verification score
                $verification = $this->verificationService->calculateVerificationScore($user, $githubStats, $period);

                // Store verification record
                GitHubVerification::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'period' => $period,
                    ],
                    [
                        'github_commits' => $githubStats['commits'],
                        'github_prs_opened' => $githubStats['pull_requests_opened'],
                        'github_prs_merged' => $githubStats['pull_requests_merged'],
                        'github_lines_added' => $githubStats['lines_added'],
                        'github_lines_removed' => $githubStats['lines_removed'],
                        'github_repos_active' => $githubStats['repos_active'],
                        'github_push_events' => $githubStats['push_events'],
                        'verification_score' => $verification['score'],
                        'is_verified' => $verification['is_verified'],
                        'verification_checks' => $verification['checks'],
                        'period_start' => $since,
                        'period_end' => now(),
                        'fetched_at' => now(),
                    ]
                );

                // Update user's verification status (use the best score across periods)
                $bestVerification = $user->githubVerifications()
                    ->where('is_verified', true)
                    ->orderByDesc('verification_score')
                    ->first();

                if ($bestVerification) {
                    $user->update([
                        'is_verified' => true,
                        'verification_score' => $bestVerification->verification_score,
                        'verified_at' => $bestVerification->fetched_at,
                    ]);
                } elseif ($verification['is_verified']) {
                    $user->update([
                        'is_verified' => true,
                        'verification_score' => $verification['score'],
                        'verified_at' => now(),
                    ]);
                }

                $synced++;
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error syncing {$user->github_username}: {$e->getMessage()}");
            }

            $bar->advance();

            // Rate limiting - GitHub allows 5000 requests/hour with auth, 60 without
            usleep(200000); // 200ms delay between users
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Sync complete!");
        $this->table(
            ['Synced', 'Skipped', 'Errors'],
            [[$synced, $skipped, $errors]]
        );

        return self::SUCCESS;
    }
}
