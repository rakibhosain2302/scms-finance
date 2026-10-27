<?php

namespace SCMS\Finance\Console;

use Illuminate\Console\Command;
use SCMS\Finance\Database\Seeders\ChartOfAccountsSeeder;
use SCMS\Finance\Database\Seeders\FinanceDemoSeeder;

class InstallCommand extends Command
{
    protected $signature = 'finance:install {--demo : Also add sample savings schemes, loan products and the current financial year}';

    protected $description = 'Add the default chart of accounts (codes that already exist are kept)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => $this->option('demo') ? FinanceDemoSeeder::class : ChartOfAccountsSeeder::class, '--force' => true]);
        $this->components->info($this->option('demo') ? 'Chart of accounts and sample set-up ready.' : 'Chart of accounts ready.');

        return self::SUCCESS;
    }
}
