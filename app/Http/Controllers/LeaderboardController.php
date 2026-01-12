<?php

namespace App\Http\Controllers;

use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function __construct(
        private LeaderboardService $leaderboardService
    ) {}

    public function index(Request $request): Response
    {
        $period = $request->get('period', 'week');
        $leaderboard = $this->leaderboardService->getLeaderboard($period, 100);

        $userStats = null;
        if ($request->user()) {
            $userStats = $this->leaderboardService->getUserStats($request->user(), $period);
        }

        return Inertia::render('leaderboard', [
            'leaderboard' => $leaderboard,
            'period' => $period,
            'userStats' => $userStats,
        ]);
    }
}
