<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IngestController extends Controller
{
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

        return response()->json([
            'success' => true,
            'metrics_recorded' => count($metricsToInsert),
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
            'recorded_at' => $recordedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
