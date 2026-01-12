<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        private LeaderboardService $leaderboardService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week');
        $limit = min((int) $request->get('limit', 100), 100);

        $leaderboard = $this->leaderboardService->getLeaderboard($period, $limit);

        return response()->json([
            'period' => $period,
            'leaderboard' => $leaderboard,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->get('period', 'week');

        $stats = $this->leaderboardService->getUserStats($user, $period);
        $modelBreakdown = $this->leaderboardService->getModelBreakdown($user, $period);

        return response()->json([
            'period' => $period,
            'stats' => $stats,
            'model_breakdown' => $modelBreakdown,
        ]);
    }
}
