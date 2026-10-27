<?php

namespace SCMS\Finance\Tests\Feature;

use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InsufficientBalanceException;
use SCMS\Finance\Models\JournalEntry;
use SCMS\Finance\Services\ReportService;
use SCMS\Finance\Services\TransferService;
use SCMS\Finance\Tests\FinanceTestCase;

class CashBankTest extends FinanceTestCase
{
    private function transfer($from, $to, string $amount, bool $post = true)
    {
        return app(TransferService::class)->create(['branch_id' => $from->branch_id, 'transfer_date' => today()->toDateString(),
            'from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => $amount], $post, $this->superAdmin());
    }

    public function test_the_opening_balance_is_a_posted_entry_against_opening_equity(): void
    {
        $entry = JournalEntry::query()->withoutBranchScope()->where('source_type', 'opening_balance')->where('source_id', $this->cashA->id)->firstOrFail();

        $this->assertSame(['1101' => ['100000.00', '0.00'], '3100' => ['0.00', '100000.00']], $this->lines($entry));
        $this->assertSame('0.00', $this->cashBalance($this->bankA->id));
    }

    public function test_cash_to_bank_and_back_is_neither_income_nor_expense(): void
    {
        $deposit = $this->transfer($this->cashA, $this->bankA, '30000');
        $this->assertSame(['1201' => ['30000.00', '0.00'], '1101' => ['0.00', '30000.00']], $this->lines($deposit->journal_entry_id));
        $this->assertSame('70000.00', $this->cashBalance($this->cashA->id));
        $this->assertSame('30000.00', $this->cashBalance($this->bankA->id));

        $this->transfer($this->bankA, $this->cashA, '5000');
        $this->assertSame('75000.00', $this->cashBalance($this->cashA->id));
        $this->assertSame('25000.00', $this->cashBalance($this->bankA->id));

        $pl = app(ReportService::class)->incomeStatement(null, today()->startOfYear(), today());
        $this->assertTrue($pl['total_income']->isZero() && $pl['total_expense']->isZero());
    }

    public function test_bank_to_bank(): void
    {
        $second = app(\SCMS\Finance\Services\CashBankService::class)->create($this->branchA->id, \SCMS\Finance\Enums\CashBankKind::Bank,
            $this->account('1201')->id, ['name' => 'Second bank'], null);
        $this->transfer($this->cashA, $this->bankA, '1000');
        $this->transfer($this->bankA, $second, '400');

        $this->assertSame('600.00', $this->cashBalance($this->bankA->id));
        $this->assertSame('400.00', $this->cashBalance($second->id));
    }

    public function test_transfers_check_accounts_and_money(): void
    {
        foreach ([fn () => $this->transfer($this->cashA, $this->cashA, '10'), fn () => $this->transfer($this->bankA, $this->cashA, '1')] as $i => $attempt) {
            try {
                $attempt();
                $this->fail("Attempt {$i} was accepted.");
            } catch (FinanceException $e) {
                $this->assertTrue($i === 0 || $e instanceof InsufficientBalanceException);
            }
        }

        $this->expectException(BranchAccessException::class);
        app(TransferService::class)->create(['branch_id' => $this->branchA->id, 'transfer_date' => today()->toDateString(),
            'from_account_id' => $this->cashA->id, 'to_account_id' => $this->cashB->id, 'amount' => '10'], true, $this->superAdmin());
    }

    public function test_a_transfer_draft_does_not_move_money_until_posted(): void
    {
        $draft = $this->transfer($this->cashA, $this->bankA, '100', false);
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));

        app(TransferService::class)->post($draft, $this->superAdmin());
        $this->assertSame('99900.00', $this->cashBalance($this->cashA->id));

        app(TransferService::class)->reverse($draft->refresh(), 'Wrong bank', $this->superAdmin());
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));
    }
}
