<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NovuService;
use Illuminate\Console\Command;

class NovuSyncSubscribersCommand extends Command
{
    protected $signature = 'novu:sync-subscribers';

    protected $description = 'Sync all users to Novu as subscribers (when Novu is enabled).';

    public function handle(NovuService $novuService): int
    {
        if (! $novuService->isEnabled()) {
            $this->warn('Novu is not enabled. Enable Novu in Configuration and set an API key.');

            return self::SUCCESS;
        }

        $users = User::all();
        $count = $users->count();
        $synced = 0;

        $this->info("Syncing {$count} user(s) to Novu...");

        foreach ($users as $user) {
            if ($novuService->syncSubscriber($user)) {
                $synced++;
            }
        }

        $this->info("Synced {$synced} of {$count} subscriber(s).");

        return self::SUCCESS;
    }
}
