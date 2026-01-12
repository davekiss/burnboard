<?php

use App\Models\Metric;
use App\Models\User;

describe('metrics endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/metrics', []);

        $response->assertUnauthorized();
    });

    it('ingests OTLP metrics with source attribute', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $payload = [
            'resourceMetrics' => [
                [
                    'scopeMetrics' => [
                        [
                            'metrics' => [
                                [
                                    'name' => 'claude_code.token.usage',
                                    'sum' => [
                                        'dataPoints' => [
                                            [
                                                'asInt' => 1000,
                                                'attributes' => [
                                                    ['key' => 'type', 'value' => ['stringValue' => 'input']],
                                                    ['key' => 'model', 'value' => ['stringValue' => 'claude-sonnet-4-20250514']],
                                                    ['key' => 'session.id', 'value' => ['stringValue' => 'test-session']],
                                                    ['key' => 'source', 'value' => ['stringValue' => 'claude_code']],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/metrics', $payload, [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'metrics_recorded' => 1]);

        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_INPUT,
            'value' => 1000,
            'model' => 'claude-sonnet-4-20250514',
            'source' => Metric::SOURCE_CLAUDE_CODE,
        ]);
    });

    it('defaults source to claude_code when not provided', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $payload = [
            'resourceMetrics' => [
                [
                    'scopeMetrics' => [
                        [
                            'metrics' => [
                                [
                                    'name' => 'claude_code.token.usage',
                                    'sum' => [
                                        'dataPoints' => [
                                            [
                                                'asInt' => 500,
                                                'attributes' => [
                                                    ['key' => 'type', 'value' => ['stringValue' => 'output']],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/metrics', $payload, [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'source' => Metric::SOURCE_CLAUDE_CODE,
        ]);
    });
});

describe('traces endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/traces', []);

        $response->assertUnauthorized();
    });

    it('ingests OTLP traces and extracts token metrics', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $payload = [
            'resourceSpans' => [
                [
                    'scopeSpans' => [
                        [
                            'spans' => [
                                [
                                    'traceId' => 'abc123',
                                    'spanId' => 'span456',
                                    'name' => 'ai.generateText.doGenerate',
                                    'startTimeUnixNano' => (string) (now()->timestamp * 1_000_000_000),
                                    'attributes' => [
                                        ['key' => 'ai.model.id', 'value' => ['stringValue' => 'claude-sonnet-4-20250514']],
                                        ['key' => 'ai.model.provider', 'value' => ['stringValue' => 'anthropic']],
                                        ['key' => 'ai.usage.promptTokens', 'value' => ['intValue' => 1500]],
                                        ['key' => 'ai.usage.completionTokens', 'value' => ['intValue' => 800]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/traces', $payload, [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'metrics_recorded' => 2]);

        // Check input tokens metric
        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_INPUT,
            'value' => 1500,
            'model' => 'anthropic/claude-sonnet-4-20250514',
            'source' => Metric::SOURCE_OPENCODE,
        ]);

        // Check output tokens metric
        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_OUTPUT,
            'value' => 800,
            'model' => 'anthropic/claude-sonnet-4-20250514',
            'source' => Metric::SOURCE_OPENCODE,
        ]);
    });

    it('handles OpenTelemetry convention attributes', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $payload = [
            'resourceSpans' => [
                [
                    'scopeSpans' => [
                        [
                            'spans' => [
                                [
                                    'traceId' => 'xyz789',
                                    'spanId' => 'span123',
                                    'name' => 'ai.streamText',
                                    'startTimeUnixNano' => (string) (now()->timestamp * 1_000_000_000),
                                    'attributes' => [
                                        ['key' => 'gen_ai.response.model', 'value' => ['stringValue' => 'gpt-4o']],
                                        ['key' => 'gen_ai.system', 'value' => ['stringValue' => 'openai']],
                                        ['key' => 'gen_ai.usage.input_tokens', 'value' => ['intValue' => 2000]],
                                        ['key' => 'gen_ai.usage.output_tokens', 'value' => ['intValue' => 1000]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/traces', $payload, [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'metrics_recorded' => 2]);

        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_INPUT,
            'value' => 2000,
            'model' => 'openai/gpt-4o',
            'source' => Metric::SOURCE_OPENCODE,
        ]);
    });

    it('ignores spans without token data', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $payload = [
            'resourceSpans' => [
                [
                    'scopeSpans' => [
                        [
                            'spans' => [
                                [
                                    'traceId' => 'no-tokens',
                                    'spanId' => 'span000',
                                    'name' => 'ai.toolCall',
                                    'startTimeUnixNano' => (string) (now()->timestamp * 1_000_000_000),
                                    'attributes' => [
                                        ['key' => 'ai.toolCall.name', 'value' => ['stringValue' => 'read_file']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/traces', $payload, [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'metrics_recorded' => 0]);
    });
});

describe('logs endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/logs', []);

        $response->assertUnauthorized();
    });

    it('ingests OTLP logs from Codex and extracts token metrics', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $payload = [
            'resourceLogs' => [
                [
                    'scopeLogs' => [
                        [
                            'logRecords' => [
                                [
                                    'timeUnixNano' => (string) (now()->timestamp * 1_000_000_000),
                                    'body' => ['stringValue' => 'codex.sse_event'],
                                    'attributes' => [
                                        ['key' => 'event.name', 'value' => ['stringValue' => 'codex.sse_event']],
                                        ['key' => 'model', 'value' => ['stringValue' => 'codex-1']],
                                        ['key' => 'conversation.id', 'value' => ['stringValue' => 'conv-abc123']],
                                        ['key' => 'input_token_count', 'value' => ['intValue' => 2500]],
                                        ['key' => 'output_token_count', 'value' => ['intValue' => 1200]],
                                        ['key' => 'cached_token_count', 'value' => ['intValue' => 500]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/logs', $payload, [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'metrics_recorded' => 3]);

        // Check input tokens metric
        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_INPUT,
            'value' => 2500,
            'model' => 'openai/codex-1',
            'session_id' => 'conv-abc123',
            'source' => Metric::SOURCE_CODEX,
        ]);

        // Check output tokens metric
        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_OUTPUT,
            'value' => 1200,
            'model' => 'openai/codex-1',
            'source' => Metric::SOURCE_CODEX,
        ]);

        // Check cached tokens metric
        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_CACHE_READ,
            'value' => 500,
            'model' => 'openai/codex-1',
            'source' => Metric::SOURCE_CODEX,
        ]);
    });

    it('ignores non-sse_event log records', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $payload = [
            'resourceLogs' => [
                [
                    'scopeLogs' => [
                        [
                            'logRecords' => [
                                [
                                    'timeUnixNano' => (string) (now()->timestamp * 1_000_000_000),
                                    'body' => ['stringValue' => 'codex.api_request'],
                                    'attributes' => [
                                        ['key' => 'event.name', 'value' => ['stringValue' => 'codex.api_request']],
                                        ['key' => 'duration_ms', 'value' => ['intValue' => 1500]],
                                    ],
                                ],
                                [
                                    'timeUnixNano' => (string) (now()->timestamp * 1_000_000_000),
                                    'body' => ['stringValue' => 'codex.user_prompt'],
                                    'attributes' => [
                                        ['key' => 'event.name', 'value' => ['stringValue' => 'codex.user_prompt']],
                                        ['key' => 'prompt_length', 'value' => ['intValue' => 100]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/logs', $payload, [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'metrics_recorded' => 0]);
    });

    it('handles logs without model info', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $payload = [
            'resourceLogs' => [
                [
                    'scopeLogs' => [
                        [
                            'logRecords' => [
                                [
                                    'timeUnixNano' => (string) (now()->timestamp * 1_000_000_000),
                                    'body' => ['stringValue' => 'codex.sse_event'],
                                    'attributes' => [
                                        ['key' => 'event.name', 'value' => ['stringValue' => 'codex.sse_event']],
                                        ['key' => 'input_token_count', 'value' => ['intValue' => 1000]],
                                        ['key' => 'output_token_count', 'value' => ['intValue' => 500]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/logs', $payload, [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'metrics_recorded' => 2]);

        $this->assertDatabaseHas('metrics', [
            'user_id' => $user->id,
            'metric_type' => Metric::TYPE_TOKENS_INPUT,
            'value' => 1000,
            'model' => null,
            'source' => Metric::SOURCE_CODEX,
        ]);
    });

    it('handles empty resourceLogs gracefully', function () {
        $user = User::factory()->withGithub()->withApiToken()->create();

        $response = $this->postJson('/api/v1/logs', ['resourceLogs' => []], [
            'Authorization' => "Bearer {$user->api_token}",
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'metrics_recorded' => 0]);
    });
});
