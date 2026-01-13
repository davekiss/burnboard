<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

# Check if configured in shell
SHELL_CONFIGURED=false
if grep -q "# Burnboard" "$SHELL_RC" 2>/dev/null; then
    SHELL_CONFIGURED=true
fi

# Remove shell config
if [ "$SHELL_CONFIGURED" = true ]; then
    sed -i.bak '/# Burnboard/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/CLAUDE_CODE_ENABLE_TELEMETRY/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_METRICS_EXPORTER/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_LOGS_EXPORTER/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_ENDPOINT/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_HEADERS/d' "$SHELL_RC" 2>/dev/null || true
    sed -i.bak '/OTEL_EXPORTER_OTLP_PROTOCOL/d' "$SHELL_RC" 2>/dev/null || true
    rm -f "${SHELL_RC}.bak" 2>/dev/null || true
    echo -e "${GREEN}✓ Burnboard configuration removed from $SHELL_RC${NC}"
else
    echo -e "${YELLOW}No Burnboard configuration found in $SHELL_RC${NC}"
fi

# Check for OpenCode config
OPENCODE_CONFIG=~/.config/opencode/opencode.jsonc
if [ -f "$OPENCODE_CONFIG" ] && grep -q '"openTelemetry"' "$OPENCODE_CONFIG" 2>/dev/null; then
    echo ""
    echo -e "${YELLOW}Note: OpenCode config found at $OPENCODE_CONFIG${NC}"
    echo -e "To disable telemetry, remove or set to false:"
    echo '  "experimental": { "openTelemetry": false }'
fi

# Check for Codex config
CODEX_CONFIG=~/.codex/config.toml
if [ -f "$CODEX_CONFIG" ] && grep -q '^\[otel\]' "$CODEX_CONFIG" 2>/dev/null; then
    echo ""
    echo -e "${YELLOW}Note: Codex config found at $CODEX_CONFIG${NC}"
    echo -e "To disable telemetry, remove or comment out the [otel] section"
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
# Connect your Claude Code, OpenCode, or OpenAI Codex telemetry to the leaderboard

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
if grep -q "OTEL_EXPORTER_OTLP_ENDPOINT.*burnboard" ~/.zshrc 2>/dev/null || grep -q "OTEL_EXPORTER_OTLP_ENDPOINT.*burnboard" ~/.bashrc 2>/dev/null; then
    echo -e "\${YELLOW}Looks like you're already set up!\${NC}"
    echo ""
    read -p "Do you want to reconfigure? (y/N) " -n 1 -r < /dev/tty
    echo
    if [[ ! \$REPLY =~ ^[Yy]\$ ]]; then
        echo -e "\${GREEN}No changes made. Happy coding!\${NC}"
        exit 0
    fi
fi

# Detect installed tools
CLAUDE_CODE_INSTALLED=false
OPENCODE_INSTALLED=false
CODEX_INSTALLED=false

if command -v claude &> /dev/null; then
    CLAUDE_CODE_INSTALLED=true
fi
if command -v opencode &> /dev/null; then
    OPENCODE_INSTALLED=true
fi
if command -v codex &> /dev/null; then
    CODEX_INSTALLED=true
fi

# Tool selection
echo -e "\${BOLD}Which AI coding tool(s) do you use?\${NC}"
echo ""

# Display menu
if [ "\$CLAUDE_CODE_INSTALLED" = true ]; then
    echo "  1) Claude Code (detected)"
else
    echo "  1) Claude Code"
fi

if [ "\$OPENCODE_INSTALLED" = true ]; then
    echo "  2) OpenCode (detected)"
else
    echo "  2) OpenCode"
fi

if [ "\$CODEX_INSTALLED" = true ]; then
    echo "  3) OpenAI Codex (detected)"
else
    echo "  3) OpenAI Codex"
fi

echo "  4) All installed tools"

read -p "Enter choice [1-4]: " -r TOOL_CHOICE < /dev/tty

SETUP_CLAUDE=false
SETUP_OPENCODE=false
SETUP_CODEX=false

case \$TOOL_CHOICE in
    1) SETUP_CLAUDE=true ;;
    2) SETUP_OPENCODE=true ;;
    3) SETUP_CODEX=true ;;
    4)
        [ "\$CLAUDE_CODE_INSTALLED" = true ] && SETUP_CLAUDE=true
        [ "\$OPENCODE_INSTALLED" = true ] && SETUP_OPENCODE=true
        [ "\$CODEX_INSTALLED" = true ] && SETUP_CODEX=true
        # If nothing installed, set up Claude Code by default
        if [ "\$SETUP_CLAUDE" = false ] && [ "\$SETUP_OPENCODE" = false ] && [ "\$SETUP_CODEX" = false ]; then
            SETUP_CLAUDE=true
        fi
        ;;
    *) SETUP_CLAUDE=true ;;
esac

echo ""

# Start device flow
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
    rm -f "\${SHELL_RC}.bak" 2>/dev/null || true
fi

# Add configuration based on tool choice
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
    echo -e "\${GREEN}✓ Claude Code telemetry configured\${NC}"
fi

if [ "\$SETUP_OPENCODE" = true ]; then
    # Add OTEL env vars for OpenCode
    cat >> "\$SHELL_RC" << EOF

# Burnboard - OpenCode Telemetry
export OTEL_EXPORTER_OTLP_ENDPOINT={$appUrl}/api
export OTEL_EXPORTER_OTLP_HEADERS="Authorization=Bearer \$API_TOKEN"
EOF
    echo -e "\${GREEN}✓ OpenCode environment configured\${NC}"

    # Configure OpenCode's opencode.jsonc
    OPENCODE_CONFIG_DIR=~/.config/opencode
    OPENCODE_CONFIG="\$OPENCODE_CONFIG_DIR/opencode.jsonc"

    mkdir -p "\$OPENCODE_CONFIG_DIR"

    if [ -f "\$OPENCODE_CONFIG" ]; then
        # Check if experimental.openTelemetry is already set
        if grep -q '"openTelemetry"' "\$OPENCODE_CONFIG" 2>/dev/null; then
            echo -e "\${YELLOW}Note: openTelemetry already configured in opencode.jsonc\${NC}"
        else
            # Add to existing config - try to add to experimental block or create it
            # This is a simple approach - for complex configs, manual edit may be needed
            echo -e "\${YELLOW}Please add to your opencode.jsonc:\${NC}"
            echo ""
            echo '  "experimental": {'
            echo '    "openTelemetry": true'
            echo '  }'
            echo ""
        fi
    else
        # Create new config file
        cat > "\$OPENCODE_CONFIG" << 'OCEOF'
{
  "experimental": {
    "openTelemetry": true
  }
}
OCEOF
        echo -e "\${GREEN}✓ Created \$OPENCODE_CONFIG with telemetry enabled\${NC}"
    fi
fi

if [ "\$SETUP_CODEX" = true ]; then
    # Configure OpenAI Codex's config.toml
    CODEX_CONFIG_DIR=~/.codex
    CODEX_CONFIG="\$CODEX_CONFIG_DIR/config.toml"

    mkdir -p "\$CODEX_CONFIG_DIR"

    if [ -f "\$CODEX_CONFIG" ]; then
        # Check if otel is already configured
        if grep -q '^\[otel\]' "\$CODEX_CONFIG" 2>/dev/null; then
            echo -e "\${YELLOW}Note: [otel] section already exists in config.toml\${NC}"
            echo -e "\${YELLOW}Please update your ~/.codex/config.toml with:\${NC}"
            echo ""
            echo '[otel]'
            echo 'exporter = { otlp-http = {'
            echo "  endpoint = \"{$appUrl}/api/v1/logs\","
            echo '  protocol = "json",'
            echo "  headers = { \"Authorization\" = \"Bearer \$API_TOKEN\" }"
            echo '}}'
            echo ""
        else
            # Append otel config to existing file
            cat >> "\$CODEX_CONFIG" << EOF

# Burnboard - OpenAI Codex Telemetry
[otel]
exporter = { otlp-http = { endpoint = "{$appUrl}/api/v1/logs", protocol = "json", headers = { "Authorization" = "Bearer \$API_TOKEN" } } }
EOF
            echo -e "\${GREEN}✓ Codex telemetry configured in \$CODEX_CONFIG\${NC}"
        fi
    else
        # Create new config file
        cat > "\$CODEX_CONFIG" << EOF
# Burnboard - OpenAI Codex Telemetry
[otel]
exporter = { otlp-http = { endpoint = "{$appUrl}/api/v1/logs", protocol = "json", headers = { "Authorization" = "Bearer \$API_TOKEN" } } }
EOF
        echo -e "\${GREEN}✓ Created \$CODEX_CONFIG with telemetry enabled\${NC}"
    fi
fi

echo ""
echo -e "\${GREEN}✓ Configuration complete\${NC}"
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

    public function deviceStart(): \Illuminate\Http\JsonResponse
    {
        // Generate device code and user code
        $deviceCode = Str::random(40);
        $userCode = strtoupper(Str::random(4).'-'.Str::random(4));

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
            'verification_uri' => config('app.url').'/device',
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
        $userCode = substr($userCode, 0, 4).'-'.substr($userCode, 4, 4);

        $deviceCode = cache()->get("user_code:{$userCode}");

        if (! $deviceCode) {
            return back()->withErrors(['user_code' => 'Invalid or expired code']);
        }

        // Store the device code in session
        session(['device_code' => $deviceCode]);

        // Use Inertia::location() to force a full browser redirect for OAuth
        return \Inertia\Inertia::location(route('auth.github'));
    }
}
