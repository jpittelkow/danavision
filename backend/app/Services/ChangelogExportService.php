<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ChangelogExportService
{
    private ?array $cachedEntries = null;

    public function __construct(
        private ChangelogService $changelogService,
        private SettingService $settingService,
    ) {}

    /**
     * Get all available version strings from the changelog.
     *
     * @return string[]
     */
    public function getAvailableVersions(): array
    {
        $entries = $this->getCachedEntries();

        return array_values(array_filter(
            array_column($entries, 'version'),
            fn (string $v) => $v !== 'Unreleased'
        ));
    }

    /**
     * Generate an AI-readable markdown export between two versions.
     */
    public function generateExport(string $fromVersion, string $toVersion): string
    {
        $allEntries = $this->getCachedEntries();
        $filtered = $this->filterEntries($allEntries, $fromVersion, $toVersion);

        if (empty($filtered)) {
            return $this->buildEmptyMarkdown($fromVersion, $toVersion);
        }

        $format = $this->settingService->get('changelog', 'export_format', 'detailed');
        $detailLevel = $this->settingService->get('changelog', 'export_detail_level', 'full');
        $instructionStyle = $this->settingService->get('changelog', 'export_instruction_style', 'step-by-step');

        $fromDate = $this->getVersionDate($allEntries, $fromVersion);
        $toDate = $this->getVersionDate($allEntries, $toVersion);
        $migrations = ($detailLevel === 'full') ? $this->detectMigrations($fromDate, $toDate) : [];

        return $this->buildMarkdown($filtered, $fromVersion, $toVersion, $migrations, $format, $detailLevel, $instructionStyle);
    }

    /**
     * Get all entries with request-scoped caching.
     */
    private function getCachedEntries(): array
    {
        return $this->cachedEntries ??= $this->changelogService->getAllEntries();
    }

    /**
     * Filter entries to those between two versions (inclusive of $to, exclusive of $from).
     *
     * @param array $entries All changelog entries (newest first)
     * @return array Filtered entries in newest-first order
     */
    private function filterEntries(array $entries, string $from, string $to): array
    {
        return array_values(array_filter($entries, function (array $entry) use ($from, $to) {
            $version = $entry['version'];

            if ($version === 'Unreleased') {
                return false;
            }

            // Include versions where: from < version <= to
            return version_compare($version, $from, '>') && version_compare($version, $to, '<=');
        }));
    }

    /**
     * Get the release date for a specific version.
     */
    private function getVersionDate(array $entries, string $version): ?string
    {
        foreach ($entries as $entry) {
            if ($entry['version'] === $version) {
                return $entry['date'] ?? null;
            }
        }

        return null;
    }

    /**
     * Detect migration files added between two dates.
     *
     * @return string[] Migration filenames
     */
    private function detectMigrations(?string $fromDate, ?string $toDate): array
    {
        if ($fromDate === null || $toDate === null) {
            return [];
        }

        $migrationsPath = base_path('database/migrations');

        if (!File::isDirectory($migrationsPath)) {
            return [];
        }

        $files = File::files($migrationsPath);
        $migrations = [];

        // Normalize dates to comparable format (YYYY_MM_DD vs YYYY-MM-DD)
        $fromNormalized = str_replace('-', '_', $fromDate);
        $toNormalized = str_replace('-', '_', $toDate);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Extract date from migration filename: 2026_03_03_000001_description.php
            if (preg_match('/^(\d{4}_\d{2}_\d{2})_/', $filename, $matches)) {
                $migrationDate = $matches[1];

                if ($migrationDate > $fromNormalized && $migrationDate <= $toNormalized) {
                    $migrations[] = $filename;
                }
            }
        }

        sort($migrations);

        return $migrations;
    }

    /**
     * Build the complete AI-readable markdown document.
     */
    private function buildMarkdown(
        array $entries,
        string $from,
        string $to,
        array $migrations,
        string $format = 'detailed',
        string $detailLevel = 'full',
        string $instructionStyle = 'step-by-step',
    ): string {
        $appName = config('app.name', 'Sourdough');
        $safeAppName = str_replace('"', '\\"', $appName);
        $generatedAt = now()->toIso8601String();
        $totalVersions = count($entries);

        // Count totals by category
        $totals = $this->countByCategory($entries);

        $md = "---\n";
        $md .= "source_app: \"{$safeAppName}\"\n";
        $md .= "export_format_version: \"1.0\"\n";
        $md .= "from_version: \"{$from}\"\n";
        $md .= "to_version: \"{$to}\"\n";
        $md .= "generated_at: \"{$generatedAt}\"\n";
        $md .= "total_versions_included: {$totalVersions}\n";
        $md .= "format: \"{$format}\"\n";
        $md .= "detail_level: \"{$detailLevel}\"\n";
        $md .= "instruction_style: \"{$instructionStyle}\"\n";
        $md .= "---\n\n";

        $md .= "# Upgrade Guide: {$appName} v{$from} → v{$to}\n\n";
        $md .= "> This document is structured for AI agent consumption. It describes all changes\n";
        $md .= "> between two {$appName} releases to help replicate or understand changes in a\n";
        $md .= "> forked codebase.\n\n";

        // Quick Summary
        $md .= "## Quick Summary\n\n";
        $md .= "- **{$totalVersions} version(s)** included in this upgrade range\n";

        foreach ($totals as $category => $count) {
            $md .= "- **{$count}** " . ucfirst($category) . "\n";
        }

        if (!empty($migrations)) {
            $md .= "- **" . count($migrations) . " database migration(s)** to run\n";
        }

        $md .= "\n";

        // Version-by-Version Changes (skip for summary format)
        if ($format === 'detailed') {
            $md .= "## Version-by-Version Changes\n\n";

            foreach ($entries as $i => $entry) {
                $dateStr = $entry['date'] ? " ({$entry['date']})" : '';
                $md .= "### v{$entry['version']}{$dateStr}\n\n";

                foreach ($entry['categories'] as $category => $items) {
                    $md .= '#### ' . ucfirst($category) . "\n\n";

                    foreach ($items as $item) {
                        $md .= "- {$item}\n";
                    }

                    $md .= "\n";
                }

                if ($i < count($entries) - 1) {
                    $md .= "---\n\n";
                }
            }
        }

        // Consolidated Changes
        $md .= "## Consolidated Changes\n\n";
        $consolidated = $this->consolidateByCategory($entries);

        if (!empty($consolidated['added'] ?? [])) {
            $md .= "### All Added Features\n\n";
            foreach ($consolidated['added'] as $item) {
                $md .= "- {$item}\n";
            }
            $md .= "\n";
        }

        $breaking = array_merge(
            $consolidated['changed'] ?? [],
            $consolidated['removed'] ?? [],
            $consolidated['security'] ?? [],
            $consolidated['deprecated'] ?? [],
        );

        if (!empty($breaking)) {
            $md .= "### All Breaking/Notable Changes\n\n";
            foreach ($breaking as $item) {
                $md .= "- {$item}\n";
            }
            $md .= "\n";
        }

        if (!empty($consolidated['fixed'] ?? [])) {
            $md .= "### All Bug Fixes\n\n";
            foreach ($consolidated['fixed'] as $item) {
                $md .= "- {$item}\n";
            }
            $md .= "\n";
        }

        // Database Migrations (full detail level only)
        if (!empty($migrations)) {
            $md .= "## Database Migrations\n\n";
            $md .= "Migrations added between v{$from} and v{$to}:\n\n";
            $md .= "| Migration | Date |\n";
            $md .= "|-----------|------|\n";

            foreach ($migrations as $migration) {
                $date = substr($migration, 0, 10);
                $date = str_replace('_', '-', $date);
                $md .= "| {$migration} | {$date} |\n";
            }

            $md .= "\n**Action:** Run `php artisan migrate` after updating to the target version.\n\n";
        }

        // Instructions for AI Agents
        $md .= $this->buildInstructions($instructionStyle);

        return $md;
    }

    /**
     * Build the AI agent instructions section based on the configured style.
     */
    private function buildInstructions(string $style): string
    {
        if ($style === 'minimal') {
            return "## Instructions\n\nApply changes in order. Run migrations. Run tests.\n";
        }

        if ($style === 'checklist') {
            $md = "## Instructions for AI Agents\n\n";
            $md .= "- [ ] Read this document fully before starting\n";
            $md .= "- [ ] Run database migrations in order\n";
            $md .= "- [ ] Review `backend/config/settings-schema.php` for new settings keys\n";
            $md .= "- [ ] Run `npm install` in frontend if dependencies changed\n";
            $md .= "- [ ] Review breaking changes (Changed, Removed, Security categories)\n";
            $md .= "- [ ] Merge carefully with fork-specific customizations\n";
            $md .= "- [ ] Run `php artisan test` and `npm test` to verify\n";

            return $md;
        }

        // Default: step-by-step
        $md = "## Instructions for AI Agents\n\n";
        $md .= "When applying these changes to a forked codebase:\n\n";
        $md .= "1. **Read this document top-to-bottom** to understand the full scope of changes.\n";
        $md .= "2. **Database migrations** must be run in order. Check the Migrations section above.\n";
        $md .= "3. **Configuration changes**: Review `backend/config/settings-schema.php` for any new settings keys.\n";
        $md .= "4. **Frontend changes**: Run `npm install` in the frontend directory if dependencies changed.\n";
        $md .= "5. **Breaking changes** are listed under \"Changed\", \"Removed\", and \"Security\" categories — review these carefully.\n";
        $md .= "6. **Fork-specific customizations**: If your fork has modified files mentioned in the changelog entries, merge carefully rather than overwriting.\n";
        $md .= "7. **Test after applying**: Run `php artisan test` and `npm test` to verify nothing is broken.\n";

        return $md;
    }

    /**
     * Build a markdown document for the case where no entries match the range.
     */
    private function buildEmptyMarkdown(string $from, string $to): string
    {
        $appName = config('app.name', 'Sourdough');

        $md = "# Upgrade Guide: {$appName} v{$from} → v{$to}\n\n";
        $md .= "No changelog entries found between these versions.\n\n";
        $md .= "This could mean:\n";
        $md .= "- The version range is invalid\n";
        $md .= "- The versions are identical or adjacent with no recorded changes\n";

        return $md;
    }

    /**
     * Count total items per category across all entries.
     *
     * @return array<string, int>
     */
    private function countByCategory(array $entries): array
    {
        $totals = [];

        foreach ($entries as $entry) {
            foreach ($entry['categories'] as $category => $items) {
                $totals[$category] = ($totals[$category] ?? 0) + count($items);
            }
        }

        return $totals;
    }

    /**
     * Consolidate all items by category across all entries.
     *
     * @return array<string, string[]>
     */
    private function consolidateByCategory(array $entries): array
    {
        $consolidated = [];

        foreach ($entries as $entry) {
            foreach ($entry['categories'] as $category => $items) {
                foreach ($items as $item) {
                    $consolidated[$category][] = $item;
                }
            }
        }

        return $consolidated;
    }
}
