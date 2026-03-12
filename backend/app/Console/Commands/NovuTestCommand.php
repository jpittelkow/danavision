<?php

namespace App\Console\Commands;

use App\Services\NovuService;
use Illuminate\Console\Command;

class NovuTestCommand extends Command
{
    protected $signature = 'novu:test';

    protected $description = 'Test Novu connection and list workflows (when Novu is enabled).';

    public function handle(NovuService $novuService): int
    {
        if (! $novuService->isEnabled()) {
            $this->warn('Novu is not enabled. Enable Novu in Configuration and set an API key.');

            return self::SUCCESS;
        }

        $result = $novuService->testConnection();

        if ($result['success']) {
            $this->info('Novu connection successful.');

            return self::SUCCESS;
        }

        $this->error('Novu connection failed: '.($result['error'] ?? 'Unknown error'));

        return self::FAILURE;
    }
}
