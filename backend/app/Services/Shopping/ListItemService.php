<?php

namespace App\Services\Shopping;

use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;

class ListItemService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Add a new item to a shopping list.
     */
    public function addItem(ShoppingList $list, User $user, array $data): ListItem
    {
        $item = new ListItem($data);
        $item->shopping_list_id = $list->id;
        $item->added_by_user_id = $user->id;
        $item->save();

        $this->auditService->log('list_item.created', $item, [], $data, $user->id);

        return $item;
    }

    /**
     * Update an existing list item.
     */
    public function updateItem(ListItem $item, array $data): ListItem
    {
        $oldValues = $item->getAttributes();
        $item->update($data);

        $this->auditService->log('list_item.updated', $item, $oldValues, $data);

        return $item;
    }

    /**
     * Delete a list item.
     */
    public function deleteItem(ListItem $item): void
    {
        $this->auditService->log('list_item.deleted', $item, $item->getAttributes());

        $item->delete();
    }

    /**
     * Mark an item as purchased, optionally recording the purchase price.
     */
    public function markPurchased(ListItem $item, ?float $price = null): ListItem
    {
        $updateData = [
            'is_purchased' => true,
            'purchased_at' => now(),
        ];

        if ($price !== null) {
            $updateData['purchased_price'] = $price;
        }

        $item->update($updateData);

        $this->auditService->log('list_item.purchased', $item, [], $updateData);

        return $item;
    }

    /**
     * Unmark an item as purchased.
     */
    public function unmarkPurchased(ListItem $item): ListItem
    {
        $item->update([
            'is_purchased' => false,
            'purchased_at' => null,
            'purchased_price' => null,
        ]);

        return $item;
    }

    /**
     * Update the price of an item and create a PriceHistory record.
     */
    public function updatePrice(ListItem $item, float $price, string $retailer, string $source = 'manual'): ListItem
    {
        $previousPrice = $item->current_price;

        $lowestPrice = $item->lowest_price;
        if ($lowestPrice === null || $price < (float) $lowestPrice) {
            $lowestPrice = $price;
        }

        $highestPrice = $item->highest_price;
        if ($highestPrice === null || $price > (float) $highestPrice) {
            $highestPrice = $price;
        }

        $item->update([
            'current_price' => $price,
            'previous_price' => $previousPrice,
            'lowest_price' => $lowestPrice,
            'highest_price' => $highestPrice,
            'current_retailer' => $retailer,
            'last_checked_at' => now(),
        ]);

        PriceHistory::create([
            'list_item_id' => $item->id,
            'price' => $price,
            'retailer' => $retailer,
            'source' => $source,
            'captured_at' => now(),
        ]);

        $this->auditService->log('list_item.refreshed', $item, [], [
            'price' => $price,
            'retailer' => $retailer,
            'source' => $source,
            'previous_price' => $previousPrice,
        ]);

        return $item;
    }

    /**
     * Get price history for an item ordered by captured_at descending.
     */
    public function getHistory(ListItem $item): Collection
    {
        return $item->priceHistory()
            ->orderBy('captured_at', 'desc')
            ->get();
    }
}
