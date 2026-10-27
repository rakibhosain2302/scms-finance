<?php

namespace SCMS\Finance\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use SCMS\Finance\Contracts\BranchAccess;
use SCMS\Finance\Contracts\FinancialYearResolver;
use SCMS\Finance\Contracts\MemberDirectory;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\NitBranchAccess;
use SCMS\Finance\Tests\TestCase;
use Symfony\Component\Finder\Finder;

/** The package boots inside an app with nit-master; no database needed. */
class PackageTest extends TestCase
{
    public function test_service_provider_registers_config_bindings_and_routes(): void
    {
        $this->assertSame('BDT', config('scms-finance.currency'));
        $this->assertInstanceOf(NitBranchAccess::class, app(BranchAccess::class));
        $this->assertInstanceOf(MemberDirectory::class, app(MemberDirectory::class));
        $this->assertInstanceOf(FinancialYearResolver::class, app(FinancialYearResolver::class));

        foreach (['finance.dashboard', 'finance.journals.post', 'finance.savings-transactions.reverse', 'finance.loans.disburse',
            'finance.loan-repayments.store', 'finance.incomes.reverse', 'finance.expenses.post', 'finance.transfers.store',
            'finance.reports.trial-balance', 'finance.reports.balance-sheet', 'finance.financial-years.close'] as $name) {
            $this->assertTrue(Route::has($name), "Route {$name} is missing");
        }
        $this->assertStringEndsWith('/finance/reports/cashbook', route('finance.reports.cashbook'));
    }

    public function test_every_route_requires_sign_in(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'finance.') && ! str_starts_with((string) $route->getName(), 'finance.api.')) {
                $this->assertContains('auth', $route->gatherMiddleware(), $route->getName().' is open to guests');
            }
        }
    }

    public function test_abilities_and_menu_reach_nit_master(): void
    {
        $groups = config('ipermissions');
        $this->assertArrayHasKey('Finance: Savings', $groups);
        $this->assertSame('Take savings deposits', $groups['Finance: Savings'][Abilities::SAVINGS_DEPOSIT]);
        $this->assertContains(Abilities::REVERSE, Abilities::all());
        $this->assertNotContains('icon', Abilities::all());

        // The role form reads them through nit-master's own parser.
        $configGroups = \Nit\Master\Http\Controllers\RoleController::configGroups();
        $this->assertContains(Abilities::LOAN_APPROVE, array_column($configGroups['Finance: Loans'], 'permission'));

        $this->assertIsArray(config('sidebar.finance'));
        $this->assertIsArray(config('sidebar.master'), 'nit-master menu must stay');
    }

    public function test_every_view_compiles(): void
    {
        $files = Finder::create()->files()->in(__DIR__.'/../../resources/views')->name('*.blade.php');
        $this->assertGreaterThan(30, iterator_count($files));

        foreach ($files as $file) {
            $php = Blade::compileString($file->getContents());
            $tmp = tempnam(sys_get_temp_dir(), 'blade');
            file_put_contents($tmp, $php);
            exec('php -l '.escapeshellarg($tmp).' 2>&1', $out, $code);
            unlink($tmp);
            $this->assertSame(0, $code, $file->getRelativePathname().': '.implode("\n", $out));
            $out = [];
        }
    }
}
