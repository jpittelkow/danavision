<?php

namespace App\Console\Commands;

use App\Jobs\DailyPriceCheck;
use App\Models\ListItem;
use App\Models\User;
use Illuminate\Console\Command;

class CheckPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:check 
                            {--user= : Check prices for a specific user ID}
                            {--list= : Check prices for a specific list ID}
                            {--item= : Check prices for a specific item ID}
                            {--all : Check prices for all users (default)}
                            {--sync : Run synchronously instead of dispatching jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update prices for shopping list items';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');
        $listId = $this->option('list');
        $itemId = $this->option('item');
        $sync = $this->option('sync');

        if ($itemId) {
            $item = ListItem::find($itemId);
            if (!$item) {
                $this->error("Item with ID {$itemId} not found.");
                return self::FAILURE;
            }
            
            $this->info("Checking price for item: {$item->product_name}");
            
            if ($sync) {
                (new DailyPriceCheck(itemId: (int) $itemId))->handle();
            } else {
                dispatch(new DailyPriceCheck(itemId: (int) $itemId));
            }
            
            $this->info('Price check dispatched for item.');
            return self::SUCCESS;
        }

        if ($listId) {
            $userId = $userId ?? ListItem::whereHas('shoppingList', function ($q) use ($listId) {
                $q->where('id', $listId);
            })->first()?->shoppingList?->user_id;

            if (!$userId) {
                $this->error("List with ID {$listId} not found or has no items.");
                return self::FAILURE;
            }

            $itemCount = ListItem::whereHas('shoppingList', function ($q) use ($listId) {
                $q->where('id', $listId);
            })->where('is_purchased', false)->count();

            $this->info("Checking prices for {$itemCount} items in list {$listId}");
            
            if ($sync) {
                (new DailyPriceCheck(userId: (int) $userId, listId: (int) $listId))->handle();
            } else {
                dispatch(new DailyPriceCheck(userId: (int) $userId, listId: (int) $listId));
            }
            
            $this->info('Price check dispatched for list.');
            return self::SUCCESS;
        }

        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return self::FAILURE;
            }

            $itemCount = ListItem::whereHas('shoppingList', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('is_purchased', false)->count();

            $this->info("Checking prices for {$itemCount} items for user: {$user->name}");
            
            if ($sync) {
                (new DailyPriceCheck(userId: (int) $userId))->handle();
            } else {
                dispatch(new DailyPriceCheck(userId: (int) $userId));
            }
            
            $this->info('Price check dispatched for user.');
            return self::SUCCESS;
        }

        // Default: check all users
        $userCount = ListItem::where('is_purchased', false)
            ->join('shopping_lists', 'list_items.shopping_list_id', '=', 'shopping_lists.id')
            ->select('shopping_lists.user_id')
            ->distinct()
            ->count();

        $this->info("Dispatching price checks for {$userCount} users with active items");
        
        if ($sync) {
            (new DailyPriceCheck())->handle();
        } else {
            dispatch(new DailyPriceCheck());
        }
        
        $this->info('Daily price check dispatched.');
        return self::SUCCESS;
    }
}
