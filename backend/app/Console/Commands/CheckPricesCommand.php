<?php

namespace App\Console\Commands;

use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Services\SettingService;
use App\Services\Shopping\PriceTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPricesCommand extends Command
{
    protected $signature = 'prices:check {--list= : Check a specific list ID}';

    protected $description = 'Check prices for items that have not been refreshed within the configured interval.';

    public function handle(PriceTrackingService $priceTrackingService, SettingService $settingService): int
    {
        $intervalHours = (int) $settingService->get('price_search', 'price_check_interval_hours', 24);

        $this->info("Checking items not refreshed in {$intervalHours} hours...");

        $query = ListItem::where('is_purchased', false)
            ->where(function ($q) use ($intervalHours) {
                $q->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', now()->subHours($intervalHours));
            });

        if ($listId = $this->option('list')) {
            $query->where('shopping_list_id', $listId);
        }

        $items = $query->with('shoppingList')->get();

        if ($items->isEmpty()) {
            $this->info('No items need price checking.');
            return self::SUCCESS;
        }

        $this->info("Found {$items->count()} items to check.");

        $success = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                $priceTrackingService->refreshItem($item);
                $success++;
                $this->line("  Checked: {$item->product_name}");
            } catch (\Exception $e) {
                $failed++;
                Log::warning('CheckPricesCommand: item refresh failed', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  Failed: {$item->product_name} — {$e->getMessage()}");
            }
        }

        $this->info("Done. {$success} checked, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
