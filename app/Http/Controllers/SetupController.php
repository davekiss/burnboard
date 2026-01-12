<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SetupController extends Controller
{
    public function __construct(
        private LeaderboardService $leaderboardService
    ) {}

    public function uninstallScript(): Response
    {
        $script = <<<'BASH'
#!/bin/bash

# Burnboard Uninstall Script

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo -e "${YELLOW}🔥 Burnboard Uninstall${NC}"
echo ""

# Find shell config
if [ -f ~/.zshrc ]; then
    SHELL_RC=~/.zshrc
elif [ -f ~/.bashrc ]; then
    SHELL_RC=~/.bashrc
else
    SHELL_RC=~/.profile
fi

# Check if configured
CONFIGURED=false
if grep -q "# Burnboard" "$SHELL_RC" 2>/dev/null; then
    CONFIGURED=true
fi

if [ "$CONFIGURED" = false ]; then
    echo -e "${YELLOW}Burnboard is not configured in $SHELL_RC${NC}"
fi

# Remove shell config
if [ "$CONFIGURED" = true ]; then
    sed -i.bak '/# Burnboard/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/CLAUDE_CODE_ENABLE_TELEMETRY/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_METRICS_EXPORTER/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_LOGS_EXPORTER/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_ENDPOINT/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_HEADERS/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_PROTOCOL/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/BURNBOARD_API_TOKEN/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/BURNBOARD_ENDPOINT/d' "$SHELL_RC" 2>/dev/null || true
    rm -f "${SHELL_RC}.bak" 2>/dev/null || true
    echo -e "${GREEN}✓ Burnboard configuration removed from $SHELL_RC${NC}"
fi

# Remove OpenCode plugin
OPENCODE_PLUGIN=~/.config/opencode/plugin/burnboard.ts
if [ -f "$OPENCODE_PLUGIN" ]; then
    rm -f "$OPENCODE_PLUGIN"
    echo -e "${GREEN}✓ OpenCode plugin removed${NC}"
fi

echo ""
echo -e "To complete uninstall:"
echo -e "  1. Run: ${YELLOW}source $SHELL_RC${NC}"
echo -e "  2. Delete your data at: ${YELLOW}https://burnboard.dev/dashboard${NC}"
echo ""
BASH;

        return response($script, 200)
            ->header('Content-Type', 'text/plain');
    }

    public function script(): Response
    {
        $appUrl = config('app.url');
        $script = <<<BASH
#!/bin/bash

# Burnboard Setup Script
# Connect your Claude Code or OpenCode telemetry to the leaderboard

set -e

# Colors
RED='\\033[0;31m'
GREEN='\\033[0;32m'
YELLOW='\\033[1;33m'
BLUE='\\033[0;34m'
CYAN='\\033[0;36m'
NC='\\033[0m' # No Color
BOLD='\\033[1m'

echo ""
echo -e "\${CYAN}\${BOLD}🔥 Burnboard - AI Coding Leaderboard\${NC}"
echo ""

# Check if already configured
ALREADY_CONFIGURED=false
if grep -q "OTEL_EXPORTER_OTLP_ENDPOINT.*burnboard\\|leaderai" ~/.zshrc 2>/dev/null || grep -q "OTEL_EXPORTER_OTLP_ENDPOINT.*burnboard\\|leaderai" ~/.bashrc 2>/dev/null; then
    ALREADY_CONFIGURED=true
fi
if grep -q "BURNBOARD_API_TOKEN" ~/.zshrc 2>/dev/null || grep -q "BURNBOARD_API_TOKEN" ~/.bashrc 2>/dev/null; then
    ALREADY_CONFIGURED=true
fi

if [ "\$ALREADY_CONFIGURED" = true ]; then
    echo -e "\${YELLOW}Looks like you're already set up!\${NC}"
    echo ""
    read -p "Do you want to reconfigure? (y/N) " -n 1 -r < /dev/tty
    echo
    if [[ ! \$REPLY =~ ^[Yy]\$ ]]; then
        echo -e "\${GREEN}No changes made. Happy coding!\${NC}"
        exit 0
    fi
fi

# Detect which coding agent is installed
CLAUDE_CODE_INSTALLED=false
OPENCODE_INSTALLED=false

if command -v claude &> /dev/null; then
    CLAUDE_CODE_INSTALLED=true
fi
if command -v opencode &> /dev/null; then
    OPENCODE_INSTALLED=true
fi

# Ask user which agent to configure
echo -e "\${BOLD}Which AI coding agent do you use?\${NC}"
echo ""

if [ "\$CLAUDE_CODE_INSTALLED" = true ] && [ "\$OPENCODE_INSTALLED" = true ]; then
    echo "  1) Claude Code (detected)"
    echo "  2) OpenCode (detected)"
    echo "  3) Both"
    echo ""
    read -p "Enter choice [1-3]: " -n 1 -r AGENT_CHOICE < /dev/tty
    echo
elif [ "\$CLAUDE_CODE_INSTALLED" = true ]; then
    echo "  1) Claude Code (detected)"
    echo "  2) OpenCode"
    echo ""
    read -p "Enter choice [1-2]: " -n 1 -r AGENT_CHOICE < /dev/tty
    echo
elif [ "\$OPENCODE_INSTALLED" = true ]; then
    echo "  1) Claude Code"
    echo "  2) OpenCode (detected)"
    echo ""
    read -p "Enter choice [1-2]: " -n 1 -r AGENT_CHOICE < /dev/tty
    echo
else
    echo "  1) Claude Code"
    echo "  2) OpenCode"
    echo ""
    read -p "Enter choice [1-2]: " -n 1 -r AGENT_CHOICE < /dev/tty
    echo
fi

SETUP_CLAUDE=false
SETUP_OPENCODE=false

case \$AGENT_CHOICE in
    1) SETUP_CLAUDE=true ;;
    2) SETUP_OPENCODE=true ;;
    3) SETUP_CLAUDE=true; SETUP_OPENCODE=true ;;
    *) SETUP_CLAUDE=true ;;
esac

# Start device flow
echo ""
echo -e "\${BLUE}Starting GitHub authentication...\${NC}"
echo ""

DEVICE_RESPONSE=\$(curl -sk -X POST "{$appUrl}/api/auth/device" \\
    -H "Content-Type: application/json")

DEVICE_CODE=\$(echo "\$DEVICE_RESPONSE" | grep -o '"device_code":"[^"]*' | cut -d'"' -f4)
USER_CODE=\$(echo "\$DEVICE_RESPONSE" | grep -o '"user_code":"[^"]*' | cut -d'"' -f4)
VERIFICATION_URI="{$appUrl}/device"
INTERVAL=\$(echo "\$DEVICE_RESPONSE" | grep -o '"interval":[0-9]*' | cut -d':' -f2)

if [ -z "\$DEVICE_CODE" ]; then
    echo -e "\${RED}Failed to start authentication. Please try again.\${NC}"
    exit 1
fi

echo -e "\${BOLD}To authenticate, visit:\${NC}"
echo ""
echo -e "  \${CYAN}\${VERIFICATION_URI}\${NC}"
echo ""
echo -e "\${BOLD}And enter code:\${NC}"
echo ""
echo -e "  \${GREEN}\${BOLD}\${USER_CODE}\${NC}"
echo ""

# Try to open browser
if command -v open &> /dev/null; then
    open "\$VERIFICATION_URI" 2>/dev/null || true
elif command -v xdg-open &> /dev/null; then
    xdg-open "\$VERIFICATION_URI" 2>/dev/null || true
fi

echo -e "\${YELLOW}Waiting for authorization...\${NC}"

# Poll for completion
MAX_ATTEMPTS=60
ATTEMPT=0

while [ \$ATTEMPT -lt \$MAX_ATTEMPTS ]; do
    sleep \${INTERVAL:-5}

    TOKEN_RESPONSE=\$(curl -sk -X POST "{$appUrl}/api/auth/device/token" \\
        -H "Content-Type: application/json" \\
        -d "{\"device_code\": \"\$DEVICE_CODE\"}")

    ERROR=\$(echo "\$TOKEN_RESPONSE" | grep -o '"error":"[^"]*' | cut -d'"' -f4)

    if [ -z "\$ERROR" ]; then
        API_TOKEN=\$(echo "\$TOKEN_RESPONSE" | grep -o '"api_token":"[^"]*' | cut -d'"' -f4)
        USERNAME=\$(echo "\$TOKEN_RESPONSE" | grep -o '"github_username":"[^"]*' | cut -d'"' -f4)

        if [ -n "\$API_TOKEN" ]; then
            echo ""
            echo -e "\${GREEN}✓ Authenticated as @\${USERNAME}\${NC}"
            break
        fi
    elif [ "\$ERROR" = "authorization_pending" ]; then
        ((ATTEMPT++))
        continue
    elif [ "\$ERROR" = "slow_down" ]; then
        sleep 5
        ((ATTEMPT++))
        continue
    else
        echo -e "\${RED}Authentication failed: \$ERROR\${NC}"
        exit 1
    fi

    ((ATTEMPT++))
done

if [ -z "\$API_TOKEN" ]; then
    echo -e "\${RED}Authentication timed out. Please try again.\${NC}"
    exit 1
fi

# Determine shell config file
if [ -n "\$ZSH_VERSION" ] || [ -f ~/.zshrc ]; then
    SHELL_RC=~/.zshrc
elif [ -f ~/.bashrc ]; then
    SHELL_RC=~/.bashrc
else
    SHELL_RC=~/.profile
fi

# Remove old config if exists
if [ -f "\$SHELL_RC" ]; then
    sed -i.bak '/# Burnboard/d' "\$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/CLAUDE_CODE_ENABLE_TELEMETRY/d' "\$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_METRICS_EXPORTER/d' "\$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_LOGS_EXPORTER/d' "\$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_ENDPOINT/d' "\$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_HEADERS/d' "\$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_PROTOCOL/d' "\$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/BURNBOARD_API_TOKEN/d' "\$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/BURNBOARD_ENDPOINT/d' "\$SHELL_RC" 2>/dev/null || true
    rm -f "\${SHELL_RC}.bak" 2>/dev/null || true
fi

# Add configuration based on agent choice
if [ "\$SETUP_CLAUDE" = true ]; then
    cat >> "\$SHELL_RC" << EOF

# Burnboard - Claude Code Telemetry
export CLAUDE_CODE_ENABLE_TELEMETRY=1
export OTEL_METRICS_EXPORTER=otlp
export OTEL_LOGS_EXPORTER=otlp
export OTEL_EXPORTER_OTLP_PROTOCOL=http/json
export OTEL_EXPORTER_OTLP_ENDPOINT={$appUrl}/api
export OTEL_EXPORTER_OTLP_HEADERS="Authorization=Bearer \$API_TOKEN"
EOF
    echo ""
    echo -e "\${GREEN}✓ Claude Code telemetry configured\${NC}"
fi

if [ "\$SETUP_OPENCODE" = true ]; then
    cat >> "\$SHELL_RC" << EOF

# Burnboard - OpenCode Telemetry
export BURNBOARD_API_TOKEN=\$API_TOKEN
export BURNBOARD_ENDPOINT={$appUrl}/api
EOF
    echo ""
    echo -e "\${GREEN}✓ OpenCode environment variables configured\${NC}"

    # Set up OpenCode plugin
    OPENCODE_PLUGIN_DIR=~/.config/opencode/plugin
    mkdir -p "\$OPENCODE_PLUGIN_DIR"

    # Download the burnboard plugin
    echo ""
    echo -e "\${BLUE}Installing OpenCode Burnboard plugin...\${NC}"
    curl -sk "{$appUrl}/opencode-plugin" -o "\$OPENCODE_PLUGIN_DIR/burnboard.ts"

    if [ -f "\$OPENCODE_PLUGIN_DIR/burnboard.ts" ]; then
        echo -e "\${GREEN}✓ Plugin installed to \$OPENCODE_PLUGIN_DIR/burnboard.ts\${NC}"
    else
        echo -e "\${YELLOW}⚠ Plugin download failed. You can manually install from:\${NC}"
        echo -e "  {$appUrl}/opencode-plugin"
    fi
fi

echo ""
echo -e "\${GREEN}✓ Configuration added to \$SHELL_RC\${NC}"
echo ""

# Show current leaderboard
echo -e "\${CYAN}\${BOLD}🏆 Current Leaderboard (This Week)\${NC}"
echo ""

LEADERBOARD=\$(curl -sk "{$appUrl}/api/leaderboard?limit=10")

# Parse and display leaderboard (simple version)
echo "\$LEADERBOARD" | grep -o '"github_username":"[^"]*"\\|"total_tokens":[0-9]*\\|"total_cost":[0-9.]*\\|"rank":[0-9]*' | paste - - - - | while read line; do
    RANK=\$(echo "\$line" | grep -o '"rank":[0-9]*' | cut -d':' -f2)
    USER=\$(echo "\$line" | grep -o '"github_username":"[^"]*' | cut -d'"' -f4)
    TOKENS=\$(echo "\$line" | grep -o '"total_tokens":[0-9]*' | cut -d':' -f2)
    COST=\$(echo "\$line" | grep -o '"total_cost":[0-9.]*' | cut -d':' -f2)

    # Format tokens
    if [ "\$TOKENS" -gt 1000000000 ]; then
        TOKENS_FMT="\$(echo "scale=1; \$TOKENS/1000000000" | bc)B"
    elif [ "\$TOKENS" -gt 1000000 ]; then
        TOKENS_FMT="\$(echo "scale=1; \$TOKENS/1000000" | bc)M"
    elif [ "\$TOKENS" -gt 1000 ]; then
        TOKENS_FMT="\$(echo "scale=1; \$TOKENS/1000" | bc)K"
    else
        TOKENS_FMT="\$TOKENS"
    fi

    printf "  %2s. %-20s %10s tokens  \$%s\\n" "\$RANK" "@\$USER" "\$TOKENS_FMT" "\$COST"
done

echo ""
echo -e "\${YELLOW}Your stats will appear after your next coding session.\${NC}"
echo ""
echo -e "\${BOLD}To apply changes now, run:\${NC}"
echo -e "  source \$SHELL_RC"
echo ""
echo -e "\${GREEN}Happy coding! 🚀\${NC}"
echo ""
BASH;

        return response($script, 200)
            ->header('Content-Type', 'text/plain');
    }

    public function opencodePlugin(): Response
    {
        $pluginCode = file_get_contents(base_path('.opencode/plugin/burnboard.ts'));

        return response($pluginCode, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="burnboard.ts"');
    }

    public function deviceStart(): \Illuminate\Http\JsonResponse
    {
        // Generate device code and user code
        $deviceCode = Str::random(40);
        $userCode = strtoupper(Str::random(4) . '-' . Str::random(4));

        // Store in cache for 15 minutes
        cache()->put("device:{$deviceCode}", [
            'user_code' => $userCode,
            'status' => 'pending',
            'created_at' => now(),
        ], now()->addMinutes(15));

        cache()->put("user_code:{$userCode}", $deviceCode, now()->addMinutes(15));

        return response()->json([
            'device_code' => $deviceCode,
            'user_code' => $userCode,
            'verification_uri' => config('app.url') . '/device',
            'expires_in' => 900,
            'interval' => 5,
        ]);
    }

    public function deviceToken(Request $request): \Illuminate\Http\JsonResponse
    {
        $deviceCode = $request->input('device_code');
        $data = cache()->get("device:{$deviceCode}");

        if (! $data) {
            return response()->json(['error' => 'expired_token'], 400);
        }

        if ($data['status'] === 'pending') {
            return response()->json(['error' => 'authorization_pending'], 400);
        }

        if ($data['status'] === 'completed' && isset($data['user_id'])) {
            $user = User::find($data['user_id']);

            if (! $user) {
                return response()->json(['error' => 'user_not_found'], 400);
            }

            // Clear the device code
            cache()->forget("device:{$deviceCode}");

            return response()->json([
                'api_token' => $user->api_token,
                'github_username' => $user->github_username,
            ]);
        }

        return response()->json(['error' => 'unknown_error'], 400);
    }

    public function deviceVerify(): \Inertia\Response
    {
        return \Inertia\Inertia::render('device-verify');
    }

    public function deviceConfirm(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Inertia\Response
    {
        $userCode = strtoupper(str_replace('-', '', $request->input('user_code', '')));
        $userCode = substr($userCode, 0, 4) . '-' . substr($userCode, 4, 4);

        $deviceCode = cache()->get("user_code:{$userCode}");

        if (! $deviceCode) {
            return back()->withErrors(['user_code' => 'Invalid or expired code']);
        }

        // Store the device code in session
        session(['device_code' => $deviceCode]);

        // Return the GitHub URL to frontend for redirect
        $redirectUrl = \Laravel\Socialite\Facades\Socialite::driver('github')->redirect()->getTargetUrl();

        return \Inertia\Inertia::render('device-redirect', [
            'redirectUrl' => $redirectUrl,
        ]);
    }
}
