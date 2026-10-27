<?php

namespace SCMS\Finance;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SCMS\Finance\Contracts\BranchAccess;
use SCMS\Finance\Contracts\FinancialYearResolver;
use SCMS\Finance\Contracts\MemberDirectory;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\AccountMap;

class SCMSFinanceServiceProvider extends ServiceProvider
{
    /** Events written to nit-master's activity log. */
    private const LOGGED_EVENTS = [
        Events\DepositPosted::class, Events\WithdrawalPosted::class, Events\LoanDisbursed::class, Events\LoanRepaymentPosted::class,
        Events\IncomePosted::class, Events\ExpensePosted::class, Events\TransferPosted::class, Events\JournalPosted::class,
        Events\TransactionReversed::class, Events\LoanStatusChanged::class, Events\SavingsInterestPosted::class, Events\LoanWrittenOff::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/scms-finance.php', 'scms-finance');

        $this->app->scoped(AccountMap::class);
        // Each binding can be replaced by the app (bind your own before this provider or in AppServiceProvider::register).
        $this->app->bindIf(BranchAccess::class, fn () => class_exists(\Nit\Master\Support\Branches::class)
            ? new Support\NitBranchAccess()
            : new Support\OpenBranchAccess());
        $this->app->bindIf(MemberDirectory::class, Support\EloquentMemberDirectory::class);
        $this->app->bindIf(FinancialYearResolver::class, Services\FinancialYearService::class);

        $config = $this->app->make('config');
        // nit-master's role form lists config('ipermissions'); the app's own file still wins on a clash.
        $config->set('ipermissions', array_merge(Abilities::groups(), (array) $config->get('ipermissions', [])));
        // nit-master's menu: one array per module in config('sidebar').
        if ($config->get('scms-finance.sidebar', true) && ! $config->has('sidebar.finance')) {
            $config->set('sidebar.finance', require __DIR__.'/../config/sidebar.php');
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'finance');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/scms-finance.php' => config_path('scms-finance.php')], 'scms-finance-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/finance')], 'scms-finance-views');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'scms-finance-migrations');
            $this->commands([Console\InstallCommand::class, Console\RebuildBalancesCommand::class]);
        }

        Gate::policy(Models\JournalEntry::class, Policies\JournalEntryPolicy::class);
        Gate::policy(Models\CashBankAccount::class, Policies\CashBankAccountPolicy::class);
        Gate::policy(Models\SavingsAccount::class, Policies\SavingsAccountPolicy::class);
        Gate::policy(Models\SavingsTransaction::class, Policies\SavingsTransactionPolicy::class);
        Gate::policy(Models\Loan::class, Policies\LoanPolicy::class);
        Gate::policy(Models\LoanRepayment::class, Policies\LoanRepaymentPolicy::class);
        Gate::policy(Models\Voucher::class, Policies\VoucherPolicy::class);
        Gate::policy(Models\Transfer::class, Policies\TransferPolicy::class);

        foreach (self::LOGGED_EVENTS as $event) {
            Event::listen($event, Listeners\LogFinanceActivity::class);
        }

        // @finmoney($amount): exact decimal, formatted (Bangla digits through nit-master in Bangla).
        Blade::directive('finmoney', fn ($e) => "<?php echo e(\\SCMS\\Finance\\Support\\Money::of({$e})->format()); ?>");

        if (! config('scms-finance.enabled', true)) {
            return;
        }

        if (config('scms-finance.routes.enabled', true)) {
            Route::middleware(config('scms-finance.routes.middleware', ['web', 'auth']))
                ->prefix(trim((string) config('scms-finance.routes.prefix', 'finance'), '/'))
                ->name(config('scms-finance.routes.name', 'finance.'))
                ->group(__DIR__.'/../routes/web.php');
        }

        if (config('scms-finance.api.enabled', false)) {
            Route::middleware(config('scms-finance.api.middleware', []))
                ->prefix(trim((string) config('scms-finance.api.prefix', 'api/finance'), '/'))
                ->name('finance.api.')
                ->group(__DIR__.'/../routes/api.php');
        }
    }
}
