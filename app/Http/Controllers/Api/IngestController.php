<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IngestController extends Controller
{
    public function __construct(
        private BadgeService $badgeService,
        private LevelService $levelService
    ) {}

    // Map OTLP metric names to our internal types
    private const METRIC_MAP = [
        'claude_code.token.usage' => [
            'input' => Metric::TYPE_TOKENS_INPUT,
            'output' => Metric::TYPE_TOKENS_OUTPUT,
            'cacheRead' => Metric::TYPE_TOKENS_CACHE_READ,
            'cacheCreation' => Metric::TYPE_TOKENS_CACHE_CREATION,
        ],
        'claude_code.cost.usage' => Metric::TYPE_COST,
        'claude_code.lines_of_code.count' => [
            'added' => Metric::TYPE_LINES_ADDED,
            'removed' => Metric::TYPE_LINES_REMOVED,
        ],
        'claude_code.commit.count' => Metric::TYPE_COMMITS,
        'claude_code.pull_request.count' => Metric::TYPE_PULL_REQUESTS,
        'claude_code.session.count' => Metric::TYPE_SESSIONS,
        'claude_code.active_time.total' => Metric::TYPE_ACTIVE_TIME,
    ];

    public function metrics(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();

        // OTLP metrics format has resourceMetrics array
        $resourceMetrics = $payload['resourceMetrics'] ?? [];

        $metricsToInsert = [];
        $now = now();

        foreach ($resourceMetrics as $resourceMetric) {
            $scopeMetrics = $resourceMetric['scopeMetrics'] ?? [];

            foreach ($scopeMetrics as $scopeMetric) {
                $metrics = $scopeMetric['metrics'] ?? [];

                foreach ($metrics as $metric) {
                    $metricName = $metric['name'] ?? '';
                    $parsed = $this->parseMetric($metric, $metricName, $user->id, $now);
                    $metricsToInsert = array_merge($metricsToInsert, $parsed);
                }
            }
        }

        // Bulk insert metrics
        if (! empty($metricsToInsert)) {
            Metric::insert($metricsToInsert);
        }

        // Process achievements after recording metrics
        $achievements = $this->processAchievements($user, $metricsToInsert);

        return response()->json([
            'success' => true,
            'metrics_recorded' => count($metricsToInsert),
            ...$achievements,
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // For now, we acknowledge logs but don't store them
        // Events could be stored later for detailed analysis
        return response()->json(['success' => true]);
    }

    /**
     * Ingest OTLP traces (spans) from OpenCode's AI SDK telemetry.
     * Extracts token usage from span attributes and converts to metrics.
     */
    public function traces(Request $request): JsonResponse
    {
        $user = $this->authenticateUser($request);

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();
        $resourceSpans = $payload['resourceSpans'] ?? [];

        $metricsToInsert = [];
        $now = now();

        foreach ($resourceSpans as $resourceSpan) {
            $scopeSpans = $resourceSpan['scopeSpans'] ?? [];

            foreach ($scopeSpans as $scopeSpan) {
                $spans = $scopeSpan['spans'] ?? [];

                foreach ($spans as $span) {
                    $parsed = $this->parseSpan($span, $user->id, $now);
                    $metricsToInsert = array_merge($metricsToInsert, $parsed);
                }
            }
        }

        // Bulk insert metrics
        if (! empty($metricsToInsert)) {
            Metric::insert($metricsToInsert);
        }

        // Process achievements after recording metrics
        $achievements = $this->processAchievements($user, $metricsToInsert);

        return response()->json([
            'success' => true,
            'metrics_recorded' => count($metricsToInsert),
            ...$achievements,
        ]);
    }

    /**
     * Parse an OTLP span and extract token usage metrics.
     * AI SDK spans include attributes like ai.usage.promptTokens, ai.usage.completionTokens
     */
    private function parseSpan(array $span, int $userId, Carbon $now): array
    {
        $results = [];
        $attributes = $this->parseAttributes($span['attributes'] ?? []);

        // Extract model info
        $modelId = $attributes['ai.model.id'] ?? $attributes['gen_ai.response.model'] ?? null;
        $provider = $attributes['ai.model.provider'] ?? $attributes['gen_ai.system'] ?? null;
        $model = $modelId ? ($provider ? "{$provider}/{$modelId}" : $modelId) : null;

        // Use span ID as session ID for grouping
        $traceId = $span['traceId'] ?? null;

        // Parse timestamp from span
        $timestamp = $span['startTimeUnixNano'] ?? $span['endTimeUnixNano'] ?? null;
        $recordedAt = $timestamp
            ? Carbon::createFromTimestampMs((int) ($timestamp / 1_000_000))
            : $now;

        // Extract token usage - check both AI SDK and OpenTelemetry conventions
        $promptTokens = $attributes['ai.usage.promptTokens']
            ?? $attributes['gen_ai.usage.input_tokens']
            ?? null;
        $completionTokens = $attributes['ai.usage.completionTokens']
            ?? $attributes['gen_ai.usage.output_tokens']
            ?? null;

        // Only create metrics if we have token data
        if ($promptTokens !== null && $promptTokens > 0) {
            $results[] = [
                'user_id' => $userId,
                'metric_type' => Metric::TYPE_TOKENS_INPUT,
                'value' => (int) $promptTokens,
                'model' => $model,
                'session_id' => $traceId,
                'source' => Metric::SOURCE_OPENCODE,
                'recorded_at' => $recordedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($completionTokens !== null && $completionTokens > 0) {
            $results[] = [
                'user_id' => $userId,
                'metric_type' => Metric::TYPE_TOKENS_OUTPUT,
                'value' => (int) $completionTokens,
                'model' => $model,
                'session_id' => $traceId,
                'source' => Metric::SOURCE_OPENCODE,
                'recorded_at' => $recordedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $results;
    }

    private function authenticateUser(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        return User::where('api_token', $token)->first();
    }

    private function parseMetric(array $metric, string $metricName, int $userId, Carbon $now): array
    {
        $results = [];

        if (! isset(self::METRIC_MAP[$metricName])) {
            return $results;
        }

        $mapping = self::METRIC_MAP[$metricName];

        // Get data points from sum or gauge
        $dataPoints = $metric['sum']['dataPoints'] ?? $metric['gauge']['dataPoints'] ?? [];

        foreach ($dataPoints as $dataPoint) {
            $value = $dataPoint['asDouble'] ?? $dataPoint['asInt'] ?? 0;
            $attributes = $this->parseAttributes($dataPoint['attributes'] ?? []);

            $sessionId = $attributes['session.id'] ?? null;
            $model = $attributes['model'] ?? null;
            $source = $attributes['source'] ?? Metric::SOURCE_CLAUDE_CODE;

            // Handle metrics with type attribute (tokens, lines)
            if (is_array($mapping)) {
                $type = $attributes['type'] ?? null;
                if ($type && isset($mapping[$type])) {
                    $results[] = $this->createMetricRecord(
                        $userId,
                        $mapping[$type],
                        $value,
                        $model,
                        $sessionId,
                        $source,
                        $dataPoint,
                        $now
                    );
                }
            } else {
                $results[] = $this->createMetricRecord(
                    $userId,
                    $mapping,
                    $value,
                    $model,
                    $sessionId,
                    $source,
                    $dataPoint,
                    $now
                );
            }
        }

        return $results;
    }

    private function parseAttributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $attr) {
            $key = $attr['key'] ?? '';
            $value = $attr['value']['stringValue'] ?? $attr['value']['intValue'] ?? null;
            if ($key && $value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function createMetricRecord(
        int $userId,
        string $metricType,
        float|int $value,
        ?string $model,
        ?string $sessionId,
        string $source,
        array $dataPoint,
        Carbon $now
    ): array {
        // Parse timestamp from OTLP (nanoseconds since epoch)
        $timestamp = $dataPoint['timeUnixNano'] ?? null;
        $recordedAt = $timestamp
            ? Carbon::createFromTimestampMs($timestamp / 1_000_000)
            : $now;

        return [
            'user_id' => $userId,
            'metric_type' => $metricType,
            'value' => $value,
            'model' => $model,
            'session_id' => $sessionId,
            'source' => $source,
            'recorded_at' => $recordedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Process level progression and badge awards after metrics are recorded.
     *
     * @return array{level_ups: array, tier_ups: array, badges_earned: array, current_level: array|null}
     */
    private function processAchievements(User $user, array $metricsInserted): array
    {
        // Calculate total tokens from inserted metrics
        $tokenTypes = [
            Metric::TYPE_TOKENS_INPUT,
            Metric::TYPE_TOKENS_OUTPUT,
            Metric::TYPE_TOKENS_CACHE_READ,
            Metric::TYPE_TOKENS_CACHE_CREATION,
        ];

        $totalNewTokens = 0;
        foreach ($metricsInserted as $metric) {
            if (in_array($metric['metric_type'], $tokenTypes)) {
                $totalNewTokens += (int) $metric['value'];
            }
        }

        // Update level with new tokens
        $levelResult = ['level_ups' => [], 'tier_ups' => [], 'current' => null];
        if ($totalNewTokens > 0) {
            $levelResult = $this->levelService->addTokens($user, $totalNewTokens);
        }

        // Check for badge awards
        $newBadges = $this->badgeService->checkAndAwardBadges($user);

        return [
            'level_ups' => $levelResult['level_ups'],
            'tier_ups' => $levelResult['tier_ups'],
            'badges_earned' => array_map(fn ($item) => [
                'slug' => $item['badge']->slug,
                'name' => $item['badge']->name,
                'description' => $item['badge']->description,
                'category' => $item['badge']->category,
                'tier' => $item['badge']->tier,
                'icon' => $item['badge']->icon,
                'is_hidden' => $item['badge']->is_hidden,
            ], $newBadges),
            'current_level' => $levelResult['current'] ?? null,
        ];
    }
}
