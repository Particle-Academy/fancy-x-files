<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Laravel\Console;

use Illuminate\Console\Command;
use ParticleAcademy\XFiles\Registry;

/**
 * `php artisan x-files:check` — validate every registered well-known file.
 * Prints a table and exits non-zero if anything is invalid (CI-friendly).
 */
final class CheckCommand extends Command
{
    protected $signature = 'x-files:check';

    protected $description = 'Validate every registered well-known file (robots.txt, security.txt, …)';

    public function handle(Registry $registry): int
    {
        $issuesByPath = $registry->validate();

        $rows = [];
        foreach ($registry->all() as $file) {
            $path = $file->path();
            $fileIssues = $issuesByPath[$path] ?? [];
            $rows[] = [
                $path,
                $fileIssues === [] ? 'OK' : 'FAIL',
                $fileIssues === [] ? '' : implode('; ', $fileIssues),
            ];
        }

        // A globally-empty registry surfaces under the '' key.
        if (isset($issuesByPath[''])) {
            $rows[] = ['(registry)', 'FAIL', implode('; ', $issuesByPath[''])];
        }

        $this->table(['Path', 'Status', 'Issues'], $rows);

        if ($issuesByPath !== []) {
            $this->error('x-files: validation failed.');

            return self::FAILURE;
        }

        $this->info('x-files: all well-known files valid.');

        return self::SUCCESS;
    }
}
