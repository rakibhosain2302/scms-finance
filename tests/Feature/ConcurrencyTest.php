<?php

namespace SCMS\Finance\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Models\SavingsTransaction;
use SCMS\Finance\Services\SavingsService;
use SCMS\Finance\Services\SavingsTransactionService;
use SCMS\Finance\Services\TransactionNumberService;
use SCMS\Finance\Tests\DatabaseTestCase;

/**
 * Two connections, committed data: while one "cashier" holds a row lock, another posting on the
 * same account or number series waits instead of reading a stale balance or number.
 */
class ConcurrencyTest extends DatabaseTestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        // Truncation runs before each test; empty the tables after too, so the committed rows do
        // not reach the next test class, which rolls back a transaction instead.
        $this->beforeApplicationDestroyed(fn () => $this->truncateDatabaseTables());
    }

    private function other(): \Illuminate\Database\Connection
    {
        config(['database.connections.finance_other' => config('database.connections.'.config('database.default'))]);

        return DB::connection('finance_other');
    }

    private function fundedAccount(): SavingsAccount
    {
        $account = app(SavingsService::class)->open(['branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id,
            'savings_scheme_id' => $this->scheme()->id, 'opened_on' => today()->toDateString()], null);
        app(SavingsTransactionService::class)->deposit($account, ['amount' => '1000', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], null);

        return $account;
    }

    public function test_a_withdrawal_waits_for_another_posting_on_the_same_account(): void
    {
        $account = $this->fundedAccount();
        $other = $this->other();
        $other->beginTransaction();
        $other->table('finance_savings_accounts')->where('id', $account->id)->lockForUpdate()->first();

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            app(SavingsTransactionService::class)->withdraw($account, ['amount' => '1000', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], null);
            $this->fail('The withdrawal did not wait for the lock.');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('lock wait timeout', $e->getMessage());
        } finally {
            $other->rollBack();
        }

        // Nothing half-written, and once the lock is gone the withdrawal goes through.
        $this->assertSame(1, SavingsTransaction::query()->count());
        app(SavingsTransactionService::class)->withdraw($account, ['amount' => '1000', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], null);
        $this->assertSame('0.00', $account->refresh()->balance);
    }

    public function test_number_series_are_serialised_and_never_repeat(): void
    {
        $numbers = app(TransactionNumberService::class);
        $first = $numbers->next('income', today());
        $other = $this->other();
        $other->beginTransaction();
        $other->table('finance_sequences')->where('series', $numbers->series('income', today()))->lockForUpdate()->first();

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $numbers->next('income', today());
            $this->fail('The counter was read while locked.');
        } catch (QueryException) {
        } finally {
            $other->rollBack();
        }

        $all = [$first];
        for ($i = 0; $i < 25; $i++) {
            $all[] = $numbers->next('income', today());
        }
        $this->assertCount(26, array_unique($all));
        $this->assertStringEndsWith('-000026', end($all));
    }
}
