<?php

namespace SCMS\Finance\Services;

use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Support\Money;

/**
 * Compares the cached balances (savings_accounts.balance, loans.principal_outstanding) with the
 * ledger and, when asked, writes the ledger figures back. The ledger always wins.
 */
class BalanceRebuildService
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    /** @return list<array{type: string, id: int, number: string, cached: string, ledger: string}> */
    public function differences(bool $fix = false): array
    {
        $found = [];

        SavingsAccount::query()->withoutBranchScope()->select(['id', 'account_number', 'balance'])->chunkById(500, function ($accounts) use (&$found, $fix) {
            $ledger = $this->ledger->netBy('savings_account_id', $accounts->pluck('id')->all());
            foreach ($accounts as $account) {
                $balance = ($ledger[$account->id] ?? Money::zero())->negated();
                if (! $balance->equals($account->balance)) {
                    $found[] = ['type' => 'savings', 'id' => $account->id, 'number' => $account->account_number, 'cached' => (string) $account->balance, 'ledger' => $balance->toString()];
                    if ($fix) {
                        DB::transaction(fn () => SavingsAccount::query()->withoutBranchScope()->lockForUpdate()->find($account->id)
                            ?->forceFill(['balance' => $this->ledger->savingsBalance($account->id)->toString()])->save());
                    }
                }
            }
        });

        Loan::query()->withoutBranchScope()->select(['id', 'loan_number', 'principal_outstanding'])->chunkById(500, function ($loans) use (&$found, $fix) {
            $ledger = $this->ledger->netBy('loan_id', $loans->pluck('id')->all(), null, LineComponent::Principal->value);
            foreach ($loans as $loan) {
                $outstanding = $ledger[$loan->id] ?? Money::zero();
                if (! $outstanding->equals($loan->principal_outstanding)) {
                    $found[] = ['type' => 'loan', 'id' => $loan->id, 'number' => $loan->loan_number, 'cached' => (string) $loan->principal_outstanding, 'ledger' => $outstanding->toString()];
                    if ($fix) {
                        DB::transaction(fn () => Loan::query()->withoutBranchScope()->lockForUpdate()->find($loan->id)
                            ?->forceFill(['principal_outstanding' => $this->ledger->loanOutstanding($loan->id)->toString()])->save());
                    }
                }
            }
        });

        return $found;
    }
}
