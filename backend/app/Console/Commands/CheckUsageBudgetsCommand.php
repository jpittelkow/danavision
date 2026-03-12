<?php

namespace App\Console\Commands;

use App\Services\UsageAlertService;
use Illuminate\Console\Command;

class CheckUsageBudgetsCommand extends Command
{
    protected $signature = 'usage:check-budgets';

    protected $description = 'Check integration usage budgets and notify admins if thresholds are exceeded.';

    public function handle(UsageAlertService $service): int
    {
        $alerts = $service->checkBudgets();

        if (empty($alerts)) {
            $this->info('All integration budgets within limits.');
            return self::SUCCESS;
        }

        foreach ($alerts as $alert) {
            $label = strtoupper($alert['integration']);
            $this->warn("{$label}: {$alert['percent']}% of budget used (\${$alert['current_cost']} / \${$alert['budget']}) - {$alert['type']}");
        }

        $this->info(count($alerts) . ' budget alert(s) triggered. Admins notified.');

        return self::SUCCESS;
    }
}
