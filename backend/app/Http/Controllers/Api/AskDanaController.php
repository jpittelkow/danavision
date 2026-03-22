<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Conversation;
use App\Services\AskDana\AskDanaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AskDanaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AskDanaService $askDanaService,
    ) {}

    /**
     * List the user's conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = $this->askDanaService->listConversations(
            $request->user(),
            (int) $request->get('per_page', 20),
        );

        return response()->json($conversations);
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = $this->askDanaService->createConversation(
            $request->user(),
            $validated['title'] ?? null,
        );

        return $this->createdResponse('Conversation created', ['data' => $conversation]);
    }

    /**
     * Get a conversation with its messages.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        $conversation = $this->askDanaService->getConversation($conversation);

        return response()->json(['data' => $conversation]);
    }

    /**
     * Update a conversation (title, pin status).
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $conversation = $this->askDanaService->updateConversation($conversation, $validated);

        return response()->json(['data' => $conversation]);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        $this->askDanaService->deleteConversation($conversation);

        return $this->successResponse('Conversation deleted');
    }

    /**
     * Send a message to a conversation and stream the response via SSE.
     */
    public function sendMessage(Request $request, Conversation $conversation): StreamedResponse|JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $content = $validated['content'];
        $wantsStream = $request->get('stream', 'true') !== 'false';

        if (!$wantsStream) {
            return $this->sendMessageSync($conversation, $content, $user);
        }

        return new StreamedResponse(function () use ($conversation, $content, $user) {
            $this->askDanaService->processMessage(
                $conversation,
                $content,
                $user,
                function (string $event, array $data) {
                    echo "event: {$event}\n";
                    echo 'data: ' . json_encode($data) . "\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                },
            );
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Non-streaming fallback — returns the complete response as JSON.
     */
    private function sendMessageSync(Conversation $conversation, string $content, $user): JsonResponse
    {
        $events = [];

        $assistantMessage = $this->askDanaService->processMessage(
            $conversation,
            $content,
            $user,
            function (string $event, array $data) use (&$events) {
                $events[] = ['event' => $event, 'data' => $data];
            },
        );

        return response()->json([
            'data' => [
                'message' => $assistantMessage,
                'events' => $events,
            ],
        ]);
    }

    /**
     * Authorize that the current user owns the conversation.
     */
    private function authorizeOwner(Request $request, Conversation $conversation): void
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this conversation.');
        }
    }
}
