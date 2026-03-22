# Ask Dana — AI Shopping Assistant

**Date:** 2026-03-21
**Status:** In Progress

## What

Added "Ask Dana" — a conversational AI assistant accessible from the sidebar that lets users interact with all their shopping data via natural language. Users can ask about prices, deals, lists, compare stores, and take actions (add items, refresh prices) through a chat interface.

## Architecture

### Backend
- **AskDanaService** — Core orchestration: manages conversations, builds system prompts, runs the tool-use loop (LLM → tool call → execute → feed back → repeat)
- **AskDanaLLMAdapter** — Calls Anthropic/OpenAI APIs directly with `tools` parameter (bypasses `LLMOrchestrator` since tool-use needs structured message arrays, not single prompt strings)
- **AskDanaToolRegistry** — 14 tools mapping to existing services (ShoppingListService, ListAnalysisService, PriceTrackingService, StoreService, etc.)
- **AskDanaController** — CRUD for conversations + SSE streaming endpoint for real-time responses
- **Conversation/ConversationMessage models** — Persistent chat history with user-scoping

### Frontend
- Two-panel layout: conversation list sidebar + chat area (Sheet drawer on mobile)
- SSE stream consumption via `useAskDanaStream` hook
- Rich message rendering with markdown, tool call indicators, streaming text display
- Suggested prompts for new conversations

### Key Decisions
1. **SSE over Reverb** — Tool-use is request-response, not broadcast
2. **Bypass LLMOrchestrator** — Tool-use needs structured messages; aggregation/council modes don't apply to conversations
3. **Write tools in tool registry** — Actions that modify data are flagged as write tools
4. **Sliding window** — Last 20 messages sent to LLM to stay within token limits

## Tools Available to Dana

| Tool | Type | Maps to |
|------|------|---------|
| get_shopping_lists | Read | ShoppingListService::getListsForUser() |
| get_list_items | Read | ListItem queries |
| get_item_details | Read | ListItem with vendorPrices + priceHistory |
| get_price_history | Read | ListItemService::getHistory() |
| get_dashboard_stats | Read | DashboardController logic |
| analyze_list_by_store | Read | ListAnalysisService::analyzeByStore() |
| get_price_drops | Read | ShoppingListService::getPriceDrops() |
| get_savings_summary | Read | ShoppingListService::getSavingsSummary() |
| get_stores | Read | StoreService::getActiveStores() |
| search_prices | Read | PriceSearchService::searchByQuery() |
| add_item_to_list | Write | ListItemService::addItem() |
| create_shopping_list | Write | ShoppingListService::createList() |
| mark_item_purchased | Write | ListItemService::markPurchased() |
| refresh_list_prices | Write | ShoppingListService::refreshPrices() |

## Files Created

### Backend
- `backend/database/migrations/2026_03_21_000003_create_conversations_table.php`
- `backend/database/migrations/2026_03_21_000004_create_conversation_messages_table.php`
- `backend/app/Models/Conversation.php`
- `backend/app/Models/ConversationMessage.php`
- `backend/app/Services/AskDana/AskDanaService.php`
- `backend/app/Services/AskDana/AskDanaLLMAdapter.php`
- `backend/app/Services/AskDana/AskDanaToolRegistry.php`
- `backend/app/Http/Controllers/Api/AskDanaController.php`

### Frontend
- `frontend/app/(dashboard)/ask-dana/page.tsx`
- `frontend/components/ask-dana/chat-view.tsx`
- `frontend/components/ask-dana/chat-message.tsx`
- `frontend/components/ask-dana/chat-input.tsx`
- `frontend/components/ask-dana/tool-call-card.tsx`
- `frontend/components/ask-dana/suggested-prompts.tsx`
- `frontend/lib/api/ask-dana.ts`
- `frontend/lib/use-ask-dana-stream.ts`

## Files Modified
- `backend/routes/api.php` — Ask Dana route group
- `backend/config/search-pages.php` — Registered Ask Dana page
- `frontend/components/sidebar.tsx` — Added nav item with Bot icon
- `frontend/lib/search-pages.ts` — Registered Ask Dana page
- `frontend/lib/help/help-content.ts` — Added help article
