<?php

namespace SCMS\Finance\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SCMS\Finance\Models\JournalEntry;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Services\LoanService;
use SCMS\Finance\Services\SavingsService;
use SCMS\Finance\Support\Money;

/** Database tests rolled back after each test, with helpers for the common set-ups. */
abstract class FinanceTestCase extends DatabaseTestCase
{
    use RefreshDatabase;

    protected function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    /** Balance of an account by code on its normal side, all branches. */
    protected function balance(string $code, ?array $branchIds = null): string
    {
        return $this->ledger()->accountBalance($this->account($code), $branchIds)->toString();
    }

    protected function cashBalance(int $cashBankAccountId): string
    {
        return $this->ledger()->cashBankBalance($cashBankAccountId)->toString();
    }

    protected function openSavings(array $schemeOverrides = []): SavingsAccount
    {
        return app(SavingsService::class)->open([
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id,
            'savings_scheme_id' => $this->scheme($schemeOverrides)->id, 'opened_on' => today()->subDays(10)->toDateString(),
        ], $this->superAdmin());
    }

    /** An approved and disbursed loan of branch A (12 monthly installments unless said otherwise). */
    protected function disbursedLoan(string $principal = '12000', array $productOverrides = [], int $count = 12): Loan
    {
        $loans = app(LoanService::class);
        $maker = $this->makeUser([], [$this->branchA->id]);
        $loan = $loans->apply([
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'loan_product_id' => $this->loanProduct($productOverrides)->id,
            'principal' => $principal, 'installments_count' => $count, 'applied_on' => today()->subDays(5)->toDateString(),
        ], $maker);
        $loans->approve($loan, $this->superAdmin());

        return $loans->disburse($loan, [
            'disbursed_on' => today()->subDays(2)->toDateString(),
            'first_due_on' => today()->addMonth()->toDateString(),
            'cash_bank_account_id' => $this->cashA->id,
        ], $this->superAdmin());
    }

    /** Lines of an entry as [code => [debit, credit]]. */
    protected function lines(JournalEntry|int $entry): array
    {
        $entry = $entry instanceof JournalEntry ? $entry : JournalEntry::query()->withoutBranchScope()->findOrFail($entry);

        return $entry->lines()->with('account')->get()->groupBy(fn ($l) => $l->account->code)
            ->map(fn ($g) => [Money::sum($g->pluck('debit'))->toString(), Money::sum($g->pluck('credit'))->toString()])->all();
    }
}
