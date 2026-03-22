<?php

namespace App\Services\Shopping;

use App\Models\ShoppingList;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;

class ShoppingListService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly PriceTrackingService $priceTrackingService,
    ) {}

    /**
     * Get all lists for a user (owned + accepted shared lists).
     */
    public function getListsForUser(User $user): Collection
    {
        $ownedLists = ShoppingList::where('user_id', $user->id)->get();

        $sharedLists = ShoppingList::whereHas('shares', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereNotNull('accepted_at')
                ->whereNull('declined_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        })->get();

        return $ownedLists->merge($sharedLists);
    }

    /**
     * Create a new shopping list for a user.
     */
    public function createList(User $user, array $data): ShoppingList
    {
        $list = new ShoppingList($data);
        $list->user_id = $user->id;
        $list->save();

        $this->auditService->log('shopping_list.created', $list, [], $data, $user->id);

        return $list;
    }

    /**
     * Update an existing shopping list.
     */
    public function updateList(ShoppingList $list, array $data): ShoppingList
    {
        $oldValues = $list->getAttributes();
        $list->update($data);

        $this->auditService->log('shopping_list.updated', $list, $oldValues, $data);

        return $list;
    }

    /**
     * Delete a shopping list.
     */
    public function deleteList(ShoppingList $list): void
    {
        $this->auditService->log('shopping_list.deleted', $list, $list->getAttributes());

        $list->delete();
    }

    /**
     * Dispatch price refresh for all items in a list (updates last_refreshed_at).
     */
    public function refreshPrices(ShoppingList $list): void
    {
        $this->priceTrackingService->refreshList($list);

        $list->update(['last_refreshed_at' => now()]);

        $this->auditService->log('shopping_list.refreshed', $list);
    }

    /**
     * Get items where current_price < previous_price across all user's lists.
     */
    public function getPriceDrops(User $user): Collection
    {
        $listIds = ShoppingList::where('user_id', $user->id)->pluck('id');

        return \App\Models\ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('previous_price')
            ->whereColumn('current_price', '<', 'previous_price')
            ->get();
    }

    /**
     * Get total savings summary (sum of highest_price - current_price) across all lists.
     */
    public function getSavingsSummary(User $user): array
    {
        $listIds = ShoppingList::where('user_id', $user->id)->pluck('id');

        $items = \App\Models\ListItem::whereIn('shopping_list_id', $listIds)
            ->whereNotNull('current_price')
            ->whereNotNull('highest_price')
            ->whereColumn('highest_price', '>', 'current_price')
            ->get();

        $totalSavings = $items->sum(function ($item) {
            return (float) $item->highest_price - (float) $item->current_price;
        });

        return [
            'total_savings' => round($totalSavings, 2),
            'items_with_savings' => $items->count(),
            'list_count' => $listIds->count(),
        ];
    }
}
