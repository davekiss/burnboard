<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $user = User::updateOrCreate(
            ['github_id' => $githubUser->getId()],
            [
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'email' => $githubUser->getEmail(),
                'github_username' => $githubUser->getNickname(),
                'avatar_url' => $githubUser->getAvatar(),
            ]
        );

        // Generate API token if user doesn't have one
        if (! $user->api_token) {
            $user->generateApiToken();
        }

        Auth::login($user, remember: true);

        // Handle device flow
        $deviceCode = session('device_code');
        if ($deviceCode) {
            $deviceData = cache()->get("device:{$deviceCode}");
            if ($deviceData) {
                cache()->put("device:{$deviceCode}", [
                    ...$deviceData,
                    'status' => 'completed',
                    'user_id' => $user->id,
                ], now()->addMinutes(5));
            }
            session()->forget('device_code');

            return redirect()->route('device.success');
        }

        return redirect()->intended(route('dashboard'));
    }
}
