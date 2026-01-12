/**
 * Burnboard Plugin for OpenCode
 *
 * Sends usage telemetry (tokens, costs) to Burnboard's leaderboard.
 * Configure with environment variables:
 *   - BURNBOARD_API_TOKEN: Your Burnboard API token (required)
 *   - BURNBOARD_ENDPOINT: API endpoint (default: https://burnboard.dev/api)
 */

import type { Plugin } from "@opencode-ai/plugin"

interface TokenMetrics {
  input: number
  output: number
  reasoning: number
  cache: {
    read: number
    write: number
  }
}

interface AssistantMessage {
  id: string
  sessionID: string
  role: "assistant"
  cost: number
  tokens: TokenMetrics
  modelID: string
  providerID: string
  finish?: string
}

// Track which messages we've already sent to avoid duplicates
const sentMessages = new Set<string>()

// Batch metrics to reduce API calls
let metricsBuffer: Array<{
  type: string
  value: number
  model: string
  sessionId: string
  timestamp: number
}> = []

let flushTimer: ReturnType<typeof setTimeout> | null = null
const FLUSH_INTERVAL = 5000 // 5 seconds

async function sendMetrics(apiToken: string, endpoint: string): Promise<void> {
  if (metricsBuffer.length === 0) return

  const metrics = [...metricsBuffer]
  metricsBuffer = []

  // Convert to OTLP format expected by Burnboard
  const resourceMetrics = [
    {
      scopeMetrics: [
        {
          metrics: buildOtlpMetrics(metrics),
        },
      ],
    },
  ]

  try {
    const response = await fetch(`${endpoint}/v1/metrics`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${apiToken}`,
      },
      body: JSON.stringify({ resourceMetrics }),
    })

    if (!response.ok) {
      console.error(`[burnboard] Failed to send metrics: ${response.status}`)
    }
  } catch (error) {
    console.error(`[burnboard] Error sending metrics:`, error)
    // Re-add metrics to buffer for retry
    metricsBuffer = [...metrics, ...metricsBuffer]
  }
}

function buildOtlpMetrics(
  metrics: Array<{
    type: string
    value: number
    model: string
    sessionId: string
    timestamp: number
  }>
): Array<object> {
  const otlpMetrics: Array<object> = []
  const timestampNano = Date.now() * 1_000_000

  // Group by metric type
  const tokenMetrics = metrics.filter((m) =>
    ["input", "output", "cacheRead", "cacheCreation"].includes(m.type)
  )
  const costMetrics = metrics.filter((m) => m.type === "cost")

  // Token usage metric
  if (tokenMetrics.length > 0) {
    otlpMetrics.push({
      name: "claude_code.token.usage",
      sum: {
        dataPoints: tokenMetrics.map((m) => ({
          asInt: Math.round(m.value),
          timeUnixNano: timestampNano,
          attributes: [
            { key: "type", value: { stringValue: m.type } },
            { key: "model", value: { stringValue: m.model } },
            { key: "session.id", value: { stringValue: m.sessionId } },
          ],
        })),
      },
    })
  }

  // Cost usage metric
  if (costMetrics.length > 0) {
    otlpMetrics.push({
      name: "claude_code.cost.usage",
      sum: {
        dataPoints: costMetrics.map((m) => ({
          asDouble: m.value,
          timeUnixNano: timestampNano,
          attributes: [
            { key: "model", value: { stringValue: m.model } },
            { key: "session.id", value: { stringValue: m.sessionId } },
          ],
        })),
      },
    })
  }

  // Session count metric
  const uniqueSessions = new Set(metrics.map((m) => m.sessionId))
  if (uniqueSessions.size > 0) {
    otlpMetrics.push({
      name: "claude_code.session.count",
      sum: {
        dataPoints: [
          {
            asInt: uniqueSessions.size,
            timeUnixNano: timestampNano,
            attributes: [],
          },
        ],
      },
    })
  }

  return otlpMetrics
}

function scheduleFlush(apiToken: string, endpoint: string): void {
  if (flushTimer) return

  flushTimer = setTimeout(() => {
    flushTimer = null
    sendMetrics(apiToken, endpoint)
  }, FLUSH_INTERVAL)
}

export const BurnboardPlugin: Plugin = async ({ client }) => {
  const apiToken = process.env.BURNBOARD_API_TOKEN
  const endpoint = process.env.BURNBOARD_ENDPOINT || "https://burnboard.dev/api"
  const debug = process.env.BURNBOARD_DEBUG === "1"

  if (!apiToken) {
    console.log(
      "[burnboard] BURNBOARD_API_TOKEN not set. Get your token at https://burnboard.dev"
    )
    return {}
  }

  console.log("[burnboard] Plugin initialized - tracking usage")
  if (debug) {
    console.log(`[burnboard] Debug mode enabled, endpoint: ${endpoint}`)
  }

  // Flush any remaining metrics on exit
  process.on("beforeExit", () => {
    if (metricsBuffer.length > 0) {
      sendMetrics(apiToken, endpoint)
    }
  })

  return {
    event: async ({ event }) => {
      if (event.type !== "message.updated") return

      const message = event.properties?.info as AssistantMessage | undefined
      if (!message || message.role !== "assistant") return

      // Only process completed messages
      if (!message.finish) return

      // Avoid duplicates
      const messageKey = `${message.sessionID}:${message.id}`
      if (sentMessages.has(messageKey)) return
      sentMessages.add(messageKey)

      // Clean up old message keys (keep last 1000)
      if (sentMessages.size > 1000) {
        const entries = Array.from(sentMessages)
        entries.slice(0, 500).forEach((key) => sentMessages.delete(key))
      }

      const { tokens, cost, modelID, sessionID } = message
      const model = `${message.providerID}/${modelID}`
      const timestamp = Date.now()

      // Add token metrics
      if (tokens.input > 0) {
        metricsBuffer.push({
          type: "input",
          value: tokens.input,
          model,
          sessionId: sessionID,
          timestamp,
        })
      }

      if (tokens.output > 0) {
        metricsBuffer.push({
          type: "output",
          value: tokens.output,
          model,
          sessionId: sessionID,
          timestamp,
        })
      }

      if (tokens.cache?.read > 0) {
        metricsBuffer.push({
          type: "cacheRead",
          value: tokens.cache.read,
          model,
          sessionId: sessionID,
          timestamp,
        })
      }

      if (tokens.cache?.write > 0) {
        metricsBuffer.push({
          type: "cacheCreation",
          value: tokens.cache.write,
          model,
          sessionId: sessionID,
          timestamp,
        })
      }

      // Add cost metric
      if (cost > 0) {
        metricsBuffer.push({
          type: "cost",
          value: cost,
          model,
          sessionId: sessionID,
          timestamp,
        })
      }

      if (debug) {
        console.log(`[burnboard] Captured metrics for ${model}:`, {
          input: tokens.input,
          output: tokens.output,
          cacheRead: tokens.cache?.read || 0,
          cacheWrite: tokens.cache?.write || 0,
          cost,
        })
      }

      // Schedule flush
      scheduleFlush(apiToken, endpoint)
    },
  }
}
