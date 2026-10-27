<?php

namespace SCMS\Finance\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Nit\Master\Models\Branch;
use Nit\Master\Models\Permission;
use Nit\Master\Models\Role;
use Nit\Master\Support\Branches;
use Orchestra\Testbench\Attributes\WithMigration;
use SCMS\Finance\Database\Seeders\ChartOfAccountsSeeder;
use SCMS\Finance\Enums\CashBankKind;
use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\FinancialYear;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Services\CashBankService;
use SCMS\Finance\Support\AccountMap;
use SCMS\Finance\Tests\Fixtures\Member;
use SCMS\Finance\Tests\Fixtures\User;

/**
 * Tests against MySQL / MariaDB (FINANCE_TEST_DB_DATABASE). The subclass picks how the database
 * is reset (FinanceTestCase: one rolled-back transaction per test). Each test starts with two branches, the default chart, an open financial year around today, a cash drawer
 * (opening balance 100,000) and a bank account per branch, and a member per branch.
 */
#[WithMigration]
abstract class DatabaseTestCase extends TestCase
{
    protected Branch $branchA;

    protected Branch $branchB;

    protected CashBankAccount $cashA;

    protected CashBankAccount $bankA;

    protected CashBankAccount $cashB;

    protected Member $memberA;

    protected Member $memberB;

    protected FinancialYear $year;

    protected ?User $admin = null;

    protected function setUp(): void
    {
        if (! env('FINANCE_TEST_DB_DATABASE')) {
            $this->markTestSkipped('Set FINANCE_TEST_DB_DATABASE (and _USERNAME / _PASSWORD) to an empty MySQL or MariaDB database.');
        }

        parent::setUp();

        app(AccountMap::class)->flush();
        Branches::flush();
        (new ChartOfAccountsSeeder())->run();

        $this->year = new FinancialYear(['name' => 'FY-current', 'starts_on' => today()->subMonths(6)->startOfMonth(), 'ends_on' => today()->addMonths(6)->endOfMonth()]);
        $this->year->forceFill(['status' => YearStatus::Open->value])->save();

        $this->branchA = Branch::query()->create(['name' => 'Alpha', 'code' => 'ALP', 'active' => true]);
        $this->branchB = Branch::query()->create(['name' => 'Beta', 'code' => 'BET', 'active' => true]);
        Branches::flush();

        $service = app(CashBankService::class);
        $cash = Account::query()->where('code', '1101')->firstOrFail();
        $bank = Account::query()->where('code', '1201')->firstOrFail();
        $this->cashA = $service->create($this->branchA->id, CashBankKind::Cash, $cash->id, ['name' => 'Alpha cash'], null, '100000', today()->subMonth());
        $this->bankA = $service->create($this->branchA->id, CashBankKind::Bank, $bank->id, ['name' => 'Alpha bank', 'account_number' => '0001'], null);
        $this->cashB = $service->create($this->branchB->id, CashBankKind::Cash, $cash->id, ['name' => 'Beta cash'], null, '50000', today()->subMonth());

        $this->memberA = Member::query()->create(['member_no' => 'A-1', 'name' => 'Rahima', 'branch_id' => $this->branchA->id]);
        $this->memberB = Member::query()->create(['member_no' => 'B-1', 'name' => 'Karim', 'branch_id' => $this->branchB->id]);
    }

    /**
     * The fixture table is created once with the package's tables (migrate:fresh), not per test:
     * on MySQL a CREATE TABLE inside RefreshDatabase's transaction commits it, and the test's rows
     * would then stay behind for the next test.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app->afterResolving('migrator', fn ($migrator) => $migrator->path(__DIR__.'/Fixtures/migrations'));
    }

    protected function superAdmin(): User
    {
        return $this->admin ??= $this->makeUser([], [], 1);
    }

    /**
     * A user whose role has the abilities, working in the branches given (none = every branch).
     *
     * @param  list<string>  $abilities
     * @param  list<int>  $branchIds
     */
    protected function makeUser(array $abilities = [], array $branchIds = [], ?int $roleId = null): User
    {
        static $n = 0;
        $n++;

        $role = $roleId === 1 ? $this->superAdminRole() : Role::query()->create(['uuid' => (string) Str::uuid(), 'title' => "Role {$n}", 'alias' => "role_{$n}"]);
        foreach ($abilities as $ability) {
            Permission::query()->create([
                'uuid' => (string) Str::uuid(), 'role_id' => $role->id, 'title' => $ability, 'permission' => $ability,
                'namespace' => 'N/A', 'controller' => 'N/A', 'method' => 'N/A', 'action' => 'N/A', 'uri' => 'N/A',
            ]);
        }

        $user = new User();
        $user->forceFill([
            'uuid' => (string) Str::uuid(), 'name' => "User {$n}", 'email' => "user{$n}@example.com",
            'password' => Hash::make('a-long-password'), 'role_id' => $role->id, 'status' => 'active',
        ])->save();

        foreach ($branchIds as $branchId) {
            DB::table('branch_user')->insert(['branch_id' => $branchId, 'user_id' => $user->id]);
        }
        Branches::flush();

        return $user;
    }

    protected function superAdminRole(): Role
    {
        return Role::query()->find(1) ?? tap(new Role(['uuid' => (string) Str::uuid(), 'title' => 'Admin', 'alias' => 'admin']), function (Role $role) {
            $role->id = 1;
            $role->save();
        });
    }

    protected function account(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }

    protected function scheme(array $overrides = []): SavingsScheme
    {
        return SavingsScheme::query()->create($overrides + [
            'code' => 'GEN'.Str::random(4), 'name' => 'General Savings', 'frequency' => 'flexible',
            'min_deposit' => '10', 'max_deposit' => null, 'minimum_balance' => '0', 'allow_withdrawal' => true,
            'liability_account_id' => $this->account('2100')->id, 'is_active' => true,
        ]);
    }

    protected function loanProduct(array $overrides = []): LoanProduct
    {
        return LoanProduct::query()->create($overrides + [
            'code' => 'LN'.Str::random(4), 'name' => 'General Loan', 'interest_method' => 'flat', 'interest_rate' => '12',
            'repayment_frequency' => 'monthly', 'min_installments' => 1, 'max_installments' => 24,
            'min_amount' => '1000', 'max_amount' => '500000', 'processing_fee_rate' => '0', 'grace_days' => 0,
            'receivable_account_id' => $this->account('1300')->id, 'interest_income_account_id' => $this->account('4200')->id,
            'penalty_income_account_id' => $this->account('4300')->id, 'fee_income_account_id' => $this->account('4400')->id,
            'is_active' => true,
        ]);
    }
}
