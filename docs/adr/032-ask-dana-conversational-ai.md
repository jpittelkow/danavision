# ADR-032: Ask Dana Conversational AI Assistant

## Status

Accepted

## Date

2026-03-21

## Context

Users need a natural-language interface to interact with their shopping data — asking questions about prices, getting recommendations, and taking actions (adding items, creating lists, refreshing prices) without navigating the UI. The assistant must leverage existing services and provide real-time feedback during multi-step tool execution.

## Decision

Implement an agentic tool-use architecture where the LLM can call specialized tools that map to existing services, with SSE streaming for real-time feedback.

### Architecture Overview

```
User Message
  → AskDanaService (orchestration)
    → AskDanaLLMAdapter (provider abstraction)
      → Claude / OpenAI API (with tool definitions)
        ← Tool call request
    → AskDanaToolRegistry (execute tool)
      → Existing service (e.g., ShoppingListService)
        ← Result
    → Feed result back to LLM
    ← Final response (or more tool calls, max 10 iterations)
  → SSE stream to frontend
```

### Core Services

| Service | Responsibility |
|---------|---------------|
| `AskDanaService` | Conversation CRUD, message processing, tool-loop orchestration (max 10 iterations, 20-message context window) |
| `AskDanaLLMAdapter` | Direct API calls to Anthropic/OpenAI with tool-use support, provider auto-detection, response normalization |
| `AskDanaToolRegistry` | Tool definitions (Anthropic format) + execution engine, maps tools to existing services |

### Tool Registry

**Read Tools** (10):
- `get_shopping_lists` — all user lists with item counts
- `get_list_items` — items in list, optionally filtered by purchase status
- `get_item_details` — full item details including vendor prices + price history
- `get_price_history` — timestamped price records
- `get_dashboard_stats` — aggregated metrics (total items, price drops, savings)
- `analyze_list_by_store` — per-store cost totals, coverage, cheapest store
- `get_price_drops` — items with price reductions across all lists
- `get_savings_summary` — total savings vs highest known prices
- `get_stores` — user's active stores with preferences
- `search_prices` — external price search via PriceSearchService

**Write Tools** (4, flagged via `isWriteTool()`):
- `add_item_to_list` — creates new item
- `create_shopping_list` — creates new list
- `mark_item_purchased` — marks item done, optionally records price
- `refresh_list_prices` — triggers async price refresh job

### Streaming Implementation

**Why SSE over Reverb**: Tool-use is request-response, not broadcast. Each message requires iterative LLM calls with tool execution in between — this maps to a single long-lived HTTP response, not pub/sub channels.

**Events emitted**:
| Event | Payload | Purpose |
|-------|---------|---------|
| `thinking` | `{}` | Processing started |
| `tool_call` | `{name, input}` | Tool invoked |
| `tool_result` | `{name, result}` | Tool completed |
| `message` | `{content, metadata}` | Final assistant response |
| `error` | `{message}` | Processing error |
| `done` | `{}` | Stream complete |

**Frontend**: `useAskDanaStream` hook manages streaming state, tool call tracking, and AbortController cancellation. Manual SSE parsing via `TextDecoder` (not EventSource, for POST support).

### Provider Resolution

Priority order for selecting LLM provider:
1. User's primary provider (must support tool-use: Claude or OpenAI)
2. User's any enabled tool-capable provider
3. System-level Claude config
4. System-level OpenAI config
5. Error if none configured

### Data Model

**Conversation**: `id, user_id, title, is_pinned, timestamps`
- `llmMessages(limit=20)` — sliding window of user + assistant messages only

**ConversationMessage**: `id, conversation_id, role, content, tool_name, tool_input, tool_output, metadata, created_at`
- Roles: `user`, `assistant`, `tool_call`, `tool_result`
- Immutable (no `updated_at`)
- Metadata tracks: tokens (input/output/total), model, provider, tool iterations

### API

All under `/api/ask-dana/`:

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/conversations` | List conversations (paginated) |
| `POST` | `/conversations` | Create new conversation |
| `GET` | `/conversations/{id}` | Get conversation with messages |
| `PATCH` | `/conversations/{id}` | Update title/pin status |
| `DELETE` | `/conversations/{id}` | Delete conversation + messages |
| `POST` | `/conversations/{id}/messages` | Send message (SSE stream) |

### Key Design Decisions

1. **Bypass LLMOrchestrator** — Tool-use needs structured message arrays; aggregation/council modes don't apply to conversational AI
2. **Sliding window (20 messages)** — Prevents context explosion while maintaining conversational coherence; tool_call/tool_result messages excluded from history after the turn
3. **Separate tool definitions** — `AskDanaToolRegistry` decouples tool logic from orchestration, making tools testable and extensible
4. **Write tool flagging** — `isWriteTool()` marks mutating tools for possible future confirmation prompts
5. **Immutable messages** — Append-only message log for auditability
6. **Metadata preservation** — Token counts, model, provider, iterations tracked per response for cost transparency

## Consequences

### Positive

- Natural-language access to all shopping features without UI navigation
- Leverages existing services — no business logic duplication
- Real-time tool execution feedback via SSE keeps users informed
- Provider flexibility (Claude + OpenAI) with automatic fallback
- Max iteration guard (10) prevents runaway tool loops

### Negative

- SSE streaming requires manual event parsing on frontend (no native EventSource for POST)
- Tool-use tokens are expensive — each iteration includes full tool definitions
- 20-message window may lose context in long conversations

### Neutral

- Dynamic system prompt includes user name, date, and quick data summary for contextual responses
- Each LLM call logged to `AIRequestLog` for usage tracking and audit

## Related Decisions

- [ADR-006](./006-llm-orchestration-modes.md) — LLMOrchestrator bypassed for tool-use, but same provider configuration used
- [ADR-031](./031-shopping-list-price-search.md) — tools map directly to shopping services
- [ADR-023](./023-audit-logging-system.md) — conversation CRUD is audited
- [ADR-029](./029-usage-tracking-alerts.md) — optional LLM usage quota integration

## Notes

- Key files: `backend/app/Services/AskDana/AskDanaService.php`, `AskDanaLLMAdapter.php`, `AskDanaToolRegistry.php`
- Models: `Conversation`, `ConversationMessage`, `AIJob`, `AIPrompt`
- Controllers: `AskDanaController`, `AIPromptController`, `AIJobController`
- Frontend: `frontend/app/(dashboard)/ask-dana/`, `frontend/components/ask-dana/`, `frontend/lib/use-ask-dana-stream.ts`
- Journal: [2026-03-21 Ask Dana AI Assistant](../journal/2026-03-21-ask-dana-ai-assistant.md)
