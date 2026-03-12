<?php

namespace App\Console\Commands;

use App\Services\SettingService;
use Illuminate\Console\Command;

class ImportEnvSettings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'settings:import-env
                            {--group= : Import only this group (e.g. mail)}';

    /**
     * The console command description.
     */
    protected $description = 'Import environment variables into the system_settings table per settings-schema';

    public function __construct(
        private SettingService $settingService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $schema = config('settings-schema', []);
        $groupOption = $this->option('group');

        if ($groupOption !== null) {
            if (!isset($schema[$groupOption])) {
                $this->error("Unknown group: {$groupOption}");
                return self::FAILURE;
            }
            $schema = [$groupOption => $schema[$groupOption]];
        }

        $imported = 0;
        $skipped = 0;

        foreach ($schema as $group => $keys) {
            foreach ($keys as $key => $keySchema) {
                $envKey = $keySchema['env'] ?? null;
                if ($envKey === null) {
                    continue;
                }
                $value = env($envKey);
                if ($value === null || $value === '') {
                    $skipped++;
                    continue;
                }
                $this->settingService->set($group, $key, $value, null);
                $this->line("  Imported {$group}.{$key} from {$envKey}");
                $imported++;
            }
        }

        $this->settingService->clearCache();
        $this->info("Import complete: {$imported} imported, {$skipped} skipped (empty or unset).");
        return self::SUCCESS;
    }
}
