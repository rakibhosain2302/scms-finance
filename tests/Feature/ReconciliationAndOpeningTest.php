<?php

namespace SCMS\Finance\Tests\Feature;

use SCMS\Finance\Enums\SavingsTransactionType;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\JournalLine;
use SCMS\Finance\Models\Reconciliation;
use SCMS\Finance\Models\SavingsTransaction;
use SCMS\Finance\Services\ExpenseService;
use SCMS\Finance\Services\ReconciliationService;
use SCMS\Finance\Services\SavingsService;
use SCMS\Finance\Services\TransferService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Tests\FinanceTestCase;

class ReconciliationAndOpeningTest extends FinanceTestCase
{
    /** 5,000 moved into the bank 5 days ago; a cheque of 1,200 written 2 days ago, not yet presented. */
    private function bankActivity(): array
    {
        $in = app(TransferService::class)->create(['branch_id' => $this->branchA->id, 'transfer_date' => today()->subDays(5)->toDateString(),
            'from_account_id' => $this->cashA->id, 'to_account_id' => $this->bankA->id, 'amount' => '5000'], true, $this->superAdmin());
        $out = app(ExpenseService::class)->create(['branch_id' => $this->branchA->id, 'voucher_date' => today()->subDays(2)->toDateString(),
            'account_id' => $this->account('5300')->id, 'cash_bank_account_id' => $this->bankA->id, 'amount' => '1200'], null, true, $this->superAdmin());

        $line = fn ($entryId) => JournalLine::query()->where('journal_entry_id', $entryId)->where('cash_bank_account_id', $this->bankA->id)->value('id');

        return [$line($in->journal_entry_id), $line($out->journal_entry_id)];
    }

    private function start(string $balance, ?string $date = null): Reconciliation
    {
        return app(ReconciliationService::class)->start($this->bankA, ['statement_date' => $date ?? today()->toDateString(), 'statement_balance' => $balance], $this->superAdmin());
    }

    public function test_the_cleared_lines_must_match_the_statement_and_the_rest_is_in_transit(): void
    {
        [$deposit, $cheque] = $this->bankActivity();
        $service = app(ReconciliationService::class);
        $reconciliation = $this->start('5000');

        $this->assertEqualsCanonicalizing([$deposit, $cheque], $service->candidates($reconciliation)->pluck('id')->all());

        try {
            $service->complete($reconciliation, $this->superAdmin());
            $this->fail('Completed with nothing cleared.');
        } catch (FinanceException $e) {
            $this->assertStringContainsString('differs from the statement', $e->getMessage());
        }

        $service->clear($reconciliation, [$deposit], $this->superAdmin());
        $summary = $service->summary($reconciliation);
        $this->assertSame('5000.00', $summary['cleared']->toString());
        $this->assertSame('0.00', $summary['difference']->toString());
        $this->assertSame('3800.00', $summary['book']->toString());
        $this->assertSame('-1200.00', $summary['uncleared']->toString(), 'the cheque not yet presented');

        $done = $service->complete($reconciliation, $this->superAdmin());
        $this->assertSame(Reconciliation::COMPLETED, $done->status);
        $this->assertSame('3800.00', $this->cashBalance($this->bankA->id), 'nothing is posted');
    }

    public function test_the_next_statement_starts_from_what_was_cleared(): void
    {
        [$deposit, $cheque] = $this->bankActivity();
        $service = app(ReconciliationService::class);
        $first = $this->start('5000', today()->subDay()->toDateString());
        $service->clear($first, [$deposit], $this->superAdmin());
        $service->complete($first, $this->superAdmin());

        $second = $this->start('3800');
        $this->assertSame([$cheque], $service->candidates($second)->pluck('id')->all(), 'a line is cleared once');

        try {
            $service->clear($second, [$deposit], $this->superAdmin());
            $this->fail('A line was cleared twice.');
        } catch (FinanceException) {
        }

        $service->clear($second, [$cheque], $this->superAdmin());
        $this->assertSame(Reconciliation::COMPLETED, $service->complete($second, $this->superAdmin())->status);

        $this->expectException(FinanceException::class);
        $this->start('3800', today()->subDays(3)->toDateString());
    }

    public function test_a_completed_reconciliation_cannot_change_and_one_draft_at_a_time(): void
    {
        [$deposit] = $this->bankActivity();
        $service = app(ReconciliationService::class);
        $reconciliation = $this->start('5000');

        try {
            $this->start('5000');
            $this->fail('Two drafts for one account.');
        } catch (FinanceException) {
        }

        $service->clear($reconciliation, [$deposit], $this->superAdmin());
        $service->complete($reconciliation, $this->superAdmin());

        $this->expectException(FinanceException::class);
        $service->clear($reconciliation, [], $this->superAdmin());
    }

    public function test_the_pages_work_and_need_the_ability(): void
    {
        [$deposit] = $this->bankActivity();
        $clerk = $this->makeUser([Abilities::CASHBANK_VIEW], [$this->branchA->id]);
        $this->actingAs($clerk)->get(route('finance.reconciliations.index'))->assertForbidden();

        $accountant = $this->makeUser([Abilities::CASHBANK_RECONCILE], [$this->branchA->id]);
        $this->actingAs($accountant)->get(route('finance.reconciliations.create'))->assertOk();
        $this->actingAs($accountant)->post(route('finance.reconciliations.store'), [
            'cash_bank_account_id' => $this->bankA->id, 'statement_date' => today()->toDateString(), 'statement_balance' => '5000',
        ])->assertRedirect();
        $reconciliation = Reconciliation::query()->firstOrFail();

        $this->actingAs($accountant)->get(route('finance.reconciliations.show', $reconciliation))->assertOk()->assertSee('5,000.00');
        $this->actingAs($accountant)->put(route('finance.reconciliations.update', $reconciliation), ['lines' => [$deposit], 'complete' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(Reconciliation::COMPLETED, $reconciliation->refresh()->status);

        $other = $this->makeUser([Abilities::CASHBANK_RECONCILE], [$this->branchB->id]);
        $this->actingAs($other)->get(route('finance.reconciliations.show', $reconciliation))->assertNotFound();
    }

    // --- Savings opening balance (go-live) ---

    public function test_an_opening_balance_credits_the_member_against_opening_equity_without_cash(): void
    {
        $account = app(SavingsService::class)->open([
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'savings_scheme_id' => $this->scheme()->id,
            'opened_on' => today()->subDays(3)->toDateString(), 'opening_balance' => '2500',
        ], $this->superAdmin());

        $this->assertSame('2500.00', $this->ledger()->savingsBalance($account->id)->toString());
        $this->assertSame('2500.00', $account->balance);
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));
        $this->assertSame(SavingsTransactionType::Opening, SavingsTransaction::query()->where('savings_account_id', $account->id)->firstOrFail()->type);
        // Opening equity: the drawers' opening balances (100,000 + 50,000) less the member's 2,500.
        $this->assertSame('147500.00', $this->balance('3100'));
    }

    public function test_an_opening_balance_needs_its_own_ability(): void
    {
        $clerk = $this->makeUser([Abilities::SAVINGS_MANAGE], [$this->branchA->id]);
        $scheme = $this->scheme();

        $this->actingAs($clerk)->post(route('finance.savings-accounts.store'), [
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'savings_scheme_id' => $scheme->id,
            'opened_on' => today()->toDateString(), 'opening_balance' => '1000',
        ])->assertSessionHasErrors('opening_balance');

        $this->expectException(FinanceException::class);
        app(SavingsService::class)->open(['branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'savings_scheme_id' => $scheme->id,
            'opened_on' => today()->toDateString(), 'opening_balance' => '1000'], $clerk);
    }

    public function test_reports_offer_a_csv_download(): void
    {
        $this->actingAs($this->superAdmin())->get(route('finance.reports.trial-balance'))->assertOk()->assertSee('data-fin-csv', false);
    }
}
