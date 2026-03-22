<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\SmartAddQueueItem;
use App\Services\SmartAdd\SmartAddService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartAddController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private SmartAddService $smartAddService
    ) {}

    /**
     * Upload image(s) or text for product identification.
     *
     * Frontend sends either:
     * - files[] (array of image files via drag-drop or file picker)
     * - text (string for text-based identification)
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required_without:text', 'array', 'max:10'],
            'files.*' => ['file', 'image', 'max:10240'],
            'text' => ['required_without:files', 'string', 'max:5000'],
            'list_id' => ['nullable', 'integer', 'exists:shopping_lists,id'],
        ]);

        $user = $request->user();
        $listId = $request->input('list_id');
        $jobs = [];

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('smart-add', 'local');
                $fullPath = storage_path('app/' . $path);
                $job = $this->smartAddService->identifyFromImage($fullPath, $user);
                if ($listId) {
                    $job->update(['related_list_id' => $listId]);
                }
                $jobs[] = $job;
            }
        } elseif ($request->filled('text')) {
            $job = $this->smartAddService->identifyFromText($request->input('text'), $user);
            if ($listId) {
                $job->update(['related_list_id' => $listId]);
            }
            $jobs[] = $job;
        }

        return $this->createdResponse('Upload processed', ['data' => $jobs]);
    }

    /**
     * Get pending queue items for the current user.
     */
    public function queue(Request $request): JsonResponse
    {
        $items = $this->smartAddService->getQueue($request->user());

        return response()->json(['data' => $items]);
    }

    /**
     * Accept an identified product from the queue.
     */
    public function acceptItem(Request $request, SmartAddQueueItem $item): JsonResponse
    {
        $this->authorizeQueueItem($request, $item);

        if (!in_array($item->status, ['ready', 'pending'])) {
            return $this->errorResponse('This item has already been processed.', 422);
        }

        $validated = $request->validate([
            'selected_index' => ['required', 'integer', 'min:0'],
            'shopping_list_id' => ['required', 'integer', 'exists:shopping_lists,id'],
        ]);

        $result = $this->smartAddService->acceptItem(
            $item,
            $validated['selected_index'],
            $validated['shopping_list_id']
        );

        return $this->successResponse('Item accepted and added to list', ['data' => $result]);
    }

    /**
     * Reject/dismiss a queue item.
     */
    public function rejectItem(Request $request, SmartAddQueueItem $item): JsonResponse
    {
        $this->authorizeQueueItem($request, $item);

        $this->smartAddService->rejectItem($item);

        return $this->deleteResponse('Item dismissed');
    }

    /**
     * Authorize that the current user owns the queue item.
     */
    private function authorizeQueueItem(Request $request, SmartAddQueueItem $item): void
    {
        if ($item->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this item.');
        }
    }
}
