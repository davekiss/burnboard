<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private LeaderboardService $leaderboardService
    ) {}

    public function show(string $username, Request $request): Response
    {
        $user = User::where('github_username', $username)->firstOrFail();
        $period = $request->get('period', 'week');

        $stats = $this->leaderboardService->getUserStats($user, $period);
        $modelBreakdown = $this->leaderboardService->getModelBreakdown($user, $period);

        // Get stats for different periods for trends
        $allTimeStats = $this->leaderboardService->getUserStats($user, 'all');

        return Inertia::render('profile', [
            'user' => [
                'github_username' => $user->github_username,
                'avatar_url' => $user->avatar_url,
                'twitter_handle' => $user->twitter_handle,
                'created_at' => $user->created_at->toISOString(),
                'is_verified' => (bool) $user->is_verified,
                'verification_score' => (int) $user->verification_score,
            ],
            'stats' => $stats,
            'allTimeStats' => $allTimeStats,
            'modelBreakdown' => $modelBreakdown,
            'period' => $period,
        ]);
    }
}
