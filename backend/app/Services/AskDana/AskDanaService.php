<?php

namespace App\Services\AskDana;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AskDanaService
{
    private const MAX_TOOL_ITERATIONS = 10;
    private const MESSAGE_WINDOW = 20;

    public function __construct(
        private readonly AskDanaLLMAdapter $llmAdapter,
        private readonly AskDanaToolRegistry $toolRegistry,
        private readonly AuditService $auditService,
    ) {}

    // ──────────────────────────────────────────────────────────────────────
    // Conversation CRUD
    // ──────────────────────────────────────────────────────────────────────

    public function listConversations(User $user, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Conversation::where('user_id', $user->id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function createConversation(User $user, ?string $title = null): Conversation
    {
        $conversation = new Conversation(['title' => $title]);
        $conversation->user_id = $user->id;
        $conversation->save();

        $this->auditService->log('conversation.created', $conversation, [], ['title' => $title], $user->id);

        return $conversation;
    }

    public function getConversation(Conversation $conversation): Conversation
    {
        return $conversation->load('messages');
    }

    public function updateConversation(Conversation $conversation, array $data): Conversation
    {
        $conversation->update($data);

        return $conversation;
    }

    public function deleteConversation(Conversation $conversation): void
    {
        $this->auditService->log('conversation.deleted', $conversation, $conversation->getAttributes());
        $conversation->delete();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Message Processing (SSE or synchronous)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Process a user message and run the tool-use loop.
     *
     * @param callable $emit  fn(string $event, array $data): void — SSE event emitter
     */
    public function processMessage(
        Conversation $conversation,
        string $userMessageText,
        User $user,
        callable $emit,
    ): ConversationMessage {
        // Persist user message
        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $userMessageText,
        ]);

        // Auto-generate title from first message
        if ($conversation->title === null) {
            $conversation->update([
                'title' => Str::limit($userMessageText, 80),
            ]);
        }

        $conversation->touch();

        $emit('thinking', ['status' => 'Processing your message...']);

        try {
            $assistantMessage = $this->runToolLoop($conversation, $user, $emit);

            $emit('message', [
                'id' => $assistantMessage->id,
                'role' => 'assistant',
                'content' => $assistantMessage->content,
                'metadata' => $assistantMessage->metadata,
                'created_at' => $assistantMessage->created_at->toIso8601String(),
            ]);

            $emit('done', []);

            return $assistantMessage;
        } catch (\Exception $e) {
            Log::error('AskDana: message processing failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $errorMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'I encountered an error while processing your request. Please try again.',
                'metadata' => ['error' => $e->getMessage()],
            ]);

            $emit('error', ['message' => 'An error occurred while processing your request.']);
            $emit('done', []);

            return $errorMessage;
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Tool-Use Loop
    // ──────────────────────────────────────────────────────────────────────

    private function runToolLoop(Conversation $conversation, User $user, callable $emit): ConversationMessage
    {
        $systemPrompt = $this->buildSystemPrompt($user);
        $tools = $this->toolRegistry->getToolDefinitions();

        // Running messages for this LLM turn (includes history + new tool exchanges)
        $llmMessages = $this->buildLLMMessages($conversation);

        $totalTokens = ['input' => 0, 'output' => 0, 'total' => 0];
        $model = null;
        $provider = null;
        $response = null;

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = $this->llmAdapter->send($user, $llmMessages, $systemPrompt, $tools);

            $model = $response['model'];
            $provider = $response['provider'];
            $totalTokens['input'] += $response['tokens']['input'];
            $totalTokens['output'] += $response['tokens']['output'];
            $totalTokens['total'] += $response['tokens']['total'];

            // No tool calls — final response
            if (empty($response['tool_calls'])) {
                return $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $response['content'] ?? '',
                    'metadata' => [
                        'tokens' => $totalTokens,
                        'model' => $model,
                        'provider' => $provider,
                        'tool_iterations' => $i,
                    ],
                ]);
            }

            // Process tool calls — execute each, then build one assistant message with all tool calls
            $toolResults = [];
            foreach ($response['tool_calls'] as $toolCall) {
                $emit('tool_call', [
                    'name' => $toolCall['name'],
                    'input' => $toolCall['input'],
                ]);

                // Persist tool_call message
                $conversation->messages()->create([
                    'role' => 'tool_call',
                    'tool_name' => $toolCall['name'],
                    'tool_input' => $toolCall['input'],
                ]);

                // Execute the tool
                $toolResult = $this->toolRegistry->execute($toolCall['name'], $toolCall['input'], $user);
                $toolResults[$toolCall['id']] = $toolResult;

                $emit('tool_result', [
                    'name' => $toolCall['name'],
                    'result' => $toolResult,
                ]);

                // Persist tool_result message
                $conversation->messages()->create([
                    'role' => 'tool_result',
                    'tool_name' => $toolCall['name'],
                    'tool_output' => $toolResult,
                ]);
            }

            // Append one assistant message with all tool calls, then all results
            $llmMessages[] = [
                'role' => 'assistant',
                'content' => $response['content'],
                'tool_calls' => $response['tool_calls'],
            ];
            foreach ($response['tool_calls'] as $toolCall) {
                $llmMessages[] = [
                    'role' => 'tool_result',
                    'tool_use_id' => $toolCall['id'],
                    'content' => $toolResults[$toolCall['id']],
                ];
            }
        }

        // Max iterations reached — return whatever we have
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response['content'] ?? 'I processed your request but reached the maximum number of steps. Here\'s what I found so far.',
            'metadata' => [
                'tokens' => $totalTokens,
                'model' => $model,
                'provider' => $provider,
                'tool_iterations' => self::MAX_TOOL_ITERATIONS,
                'max_iterations_reached' => true,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // System Prompt
    // ──────────────────────────────────────────────────────────────────────

    private function buildSystemPrompt(User $user): string
    {
        $userName = $user->name ?? 'there';
        $now = now()->format('l, F j, Y g:i A');

        // Quick data summary for context
        $listCount = ShoppingList::where('user_id', $user->id)->count();
        $activeItems = ShoppingList::where('user_id', $user->id)
            ->withCount(['items' => fn ($q) => $q->where('is_purchased', false)])
            ->get()
            ->sum('items_count');

        return <<<PROMPT
You are Dana, a friendly and knowledgeable shopping assistant for the DanaVision app. You help users manage their shopping lists, find the best deals, track prices, and make smart purchasing decisions.

Current date/time: {$now}
User: {$userName}
Their data: {$listCount} shopping list(s), {$activeItems} active (unpurchased) item(s).

## Your Capabilities
You have access to tools that let you query the user's shopping data, search for prices, analyze deals, and take actions on their behalf. Use these tools proactively when answering questions — don't guess at data you can look up.

## Guidelines
- Be conversational but concise. Lead with the answer, then offer details.
- When the user asks about prices, deals, or their lists, use the appropriate tool to get real data before responding.
- Format prices as currency (e.g., $4.99).
- When comparing stores or showing price data, use clear formatting with bullet points or numbered lists.
- If an action will modify their data (adding items, creating lists, marking purchased), confirm what you're about to do before executing.
- If you don't have enough information, ask a clarifying question.
- When presenting price history or trends, describe the pattern (rising, falling, stable) and highlight significant changes.
- If a tool returns an error, explain the issue in plain language and suggest alternatives.

## Response Formatting
- Use markdown for formatting (bold, lists, headers).
- Keep responses focused and scannable.
- For price comparisons, present data in a structured way.
PROMPT;
    }

    // ──────────────────────────────────────────────────────────────────────
    // LLM Message Building
    // ──────────────────────────────────────────────────────────────────────

    private function buildLLMMessages(Conversation $conversation): array
    {
        $dbMessages = $conversation->llmMessages(self::MESSAGE_WINDOW);

        $llmMessages = [];

        foreach ($dbMessages as $msg) {
            if ($msg->role === 'user') {
                $llmMessages[] = ['role' => 'user', 'content' => $msg->content];
            } elseif ($msg->role === 'assistant') {
                $llmMessages[] = ['role' => 'assistant', 'content' => $msg->content ?? ''];
            }
            // tool_call and tool_result are only needed within the current turn,
            // not from history (the LLM sees the final assistant response that
            // incorporated the tool results)
        }

        return $llmMessages;
    }
}
