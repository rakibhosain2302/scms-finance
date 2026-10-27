<?php

namespace SCMS\Finance\Console;

use Illuminate\Console\Command;
use SCMS\Finance\Services\BalanceRebuildService;

class RebuildBalancesCommand extends Command
{
    protected $signature = 'finance:rebuild-balances {--fix : Write the ledger figures into the cached balances}';

    protected $description = 'Compare cached savings and loan balances with the ledger';

    public function handle(BalanceRebuildService $service): int
    {
        $differences = $service->differences((bool) $this->option('fix'));

        if ($differences === []) {
            $this->components->info('Every cached balance matches the ledger.');

            return self::SUCCESS;
        }

        $this->table(['Type', 'Number', 'Cached', 'Ledger'], array_map(fn ($d) => [$d['type'], $d['number'], $d['cached'], $d['ledger']], $differences));
        $this->option('fix')
            ? $this->components->warn(count($differences).' cached balances were set to the ledger figures.')
            : $this->components->warn(count($differences).' differences. Run with --fix to correct them.');

        return $this->option('fix') ? self::SUCCESS : self::FAILURE;
    }
}
