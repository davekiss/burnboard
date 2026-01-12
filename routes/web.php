<?php

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetupController;
use App\Services\LeaderboardService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [LeaderboardController::class, 'index'])->name('home');
Route::get('/leaderboard', fn () => redirect('/'))->name('leaderboard');
Route::get('/how-it-works', fn () => Inertia::render('how-it-works'))->name('how-it-works');
Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');

// GitHub OAuth
Route::get('/auth/github', [GitHubController::class, 'redirect'])->name('auth.github');
Route::get('/auth/github/callback', [GitHubController::class, 'callback'])->name('auth.github.callback');

// Setup script
Route::get('/join', [SetupController::class, 'script'])->name('setup.script');
Route::get('/uninstall', [SetupController::class, 'uninstallScript'])->name('setup.uninstall');
Route::get('/opencode-plugin', [SetupController::class, 'opencodePlugin'])->name('setup.opencode-plugin');
Route::get('/device', [SetupController::class, 'deviceVerify'])->name('device.verify');
Route::post('/device', [SetupController::class, 'deviceConfirm'])->name('device.confirm');
Route::get('/device/success', function () {
    return Inertia::render('device-success');
})->name('device.success');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        return Inertia::render('dashboard', [
            'apiToken' => $user->api_token,
            'hasMetrics' => $user->metrics()->exists(),
        ]);
    })->name('dashboard');

    Route::delete('dashboard/stats', function () {
        request()->user()->metrics()->delete();

        return back();
    })->name('dashboard.stats.delete');
});

require __DIR__.'/settings.php';
