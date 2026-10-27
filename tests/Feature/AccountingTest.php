<?php

namespace SCMS\Finance\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Exceptions\UnbalancedJournalException;
use SCMS\Finance\Models\JournalEntry;
use SCMS\Finance\Services\JournalService;
use SCMS\Finance\Tests\FinanceTestCase;

class AccountingTest extends FinanceTestCase
{
    private function draft(string $debit = '500', string $credit = '500'): JournalEntry
    {
        return app(JournalService::class)->createDraft(
            ['branch_id' => $this->branchA->id, 'entry_date' => today()->toDateString(), 'description' => 'Stationery'],
            [
                ['account_id' => $this->account('5100')->id, 'debit' => $debit],
                ['account_id' => $this->account('1101')->id, 'credit' => $credit, 'cash_bank_account_id' => $this->cashA->id],
            ],
            $this->superAdmin(),
        );
    }

    public function test_a_balanced_entry_posts_and_the_trial_balance_balances(): void
    {
        $entry = app(JournalService::class)->post($this->draft(), $this->superAdmin());

        $this->assertSame(EntryStatus::Posted, $entry->status);
        $this->assertSame(['5100' => ['500.00', '0.00'], '1101' => ['0.00', '500.00']], $this->lines($entry));
        $this->assertSame('99500.00', $this->cashBalance($this->cashA->id));
        $tb = $this->ledger()->trialBalance(null, today());
        $this->assertTrue($tb['debit']->equals($tb['credit']));
        $this->assertSame($this->year->id, $entry->financial_year_id);
        $this->assertMatchesRegularExpression('/^JV-\d{4}-\d{6}$/', $entry->number);
    }

    public function test_an_unbalanced_entry_is_never_posted(): void
    {
        $entry = $this->draft('500', '400');

        try {
            app(JournalService::class)->post($entry, $this->superAdmin());
            $this->fail('Unbalanced entry was posted.');
        } catch (UnbalancedJournalException) {
        }

        $this->assertSame(EntryStatus::Draft, $entry->refresh()->status);
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));
    }

    public function test_a_line_must_be_a_debit_or_a_credit_never_negative(): void
    {
        $this->expectException(FinanceException::class);
        app(JournalService::class)->record(
            ['branch_id' => $this->branchA->id, 'entry_date' => today(), 'source_type' => 'test'],
            [['account' => $this->account('5100'), 'debit' => '-10'], ['account' => $this->account('4100'), 'credit' => '-10']],
            null,
        );
    }

    public function test_the_database_refuses_a_line_with_both_sides(): void
    {
        $entry = app(JournalService::class)->post($this->draft(), $this->superAdmin());

        $this->expectException(QueryException::class);
        DB::table('finance_journal_lines')->insert([
            'journal_entry_id' => $entry->id, 'account_id' => $this->account('5100')->id, 'debit' => '5', 'credit' => '5',
        ]);
    }

    public function test_a_posted_entry_cannot_be_edited_or_cancelled(): void
    {
        $journal = app(JournalService::class);
        $entry = $journal->post($this->draft(), $this->superAdmin());

        $this->expectException(InvalidStateException::class);
        $journal->updateDraft($entry, ['entry_date' => today()->toDateString(), 'description' => 'changed'], [
            ['account_id' => $this->account('5100')->id, 'debit' => '1'],
            ['account_id' => $this->account('1101')->id, 'credit' => '1', 'cash_bank_account_id' => $this->cashA->id],
        ], $this->superAdmin());
    }

    public function test_a_draft_can_be_edited_and_cancelled_without_touching_the_ledger(): void
    {
        $journal = app(JournalService::class);
        $entry = $journal->updateDraft($this->draft(), ['entry_date' => today()->toDateString(), 'description' => 'Fixed'], [
            ['account_id' => $this->account('5100')->id, 'debit' => '250'],
            ['account_id' => $this->account('1101')->id, 'credit' => '250', 'cash_bank_account_id' => $this->cashA->id],
        ], $this->superAdmin());
        $this->assertSame('250.00', $entry->total);

        $journal->cancelDraft($entry, $this->superAdmin());
        $this->assertSame(EntryStatus::Cancelled, $entry->refresh()->status);
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));
    }

    public function test_reversal_mirrors_the_entry_and_both_stay_in_history(): void
    {
        $journal = app(JournalService::class);
        $entry = $journal->post($this->draft(), $this->superAdmin());
        $reversal = $journal->reverseManual($entry, 'Wrong account', $this->superAdmin());

        $this->assertSame(EntryStatus::Reversed, $entry->refresh()->status);
        $this->assertSame($entry->id, $reversal->reversal_of_id);
        $this->assertSame(['5100' => ['0.00', '500.00'], '1101' => ['500.00', '0.00']], $this->lines($reversal));
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));
        $this->assertSame('0.00', $this->balance('5100'));
        $this->assertSame(2, JournalEntry::query()->where('source_type', 'manual')->count());

        try {
            $journal->reverseManual($entry, 'Again', $this->superAdmin());
            $this->fail('Reversed twice.');
        } catch (InvalidStateException) {
        }
        $this->expectException(InvalidStateException::class);
        $journal->reverseManual($reversal, 'Reverse the reversal', $this->superAdmin());
    }

    public function test_manual_entries_cannot_move_member_ledgers_or_cash_without_its_drawer(): void
    {
        $journal = app(JournalService::class);

        try {
            $journal->createDraft(['branch_id' => $this->branchA->id, 'entry_date' => today()->toDateString(), 'description' => 'x'], [
                ['account_id' => $this->account('2100')->id, 'debit' => '10'],
                ['account_id' => $this->account('4100')->id, 'credit' => '10'],
            ], $this->superAdmin());
            $this->fail('Member savings moved by hand.');
        } catch (FinanceException) {
        }

        $this->expectException(FinanceException::class);
        $journal->createDraft(['branch_id' => $this->branchA->id, 'entry_date' => today()->toDateString(), 'description' => 'x'], [
            ['account_id' => $this->account('1101')->id, 'debit' => '10'],
            ['account_id' => $this->account('4100')->id, 'credit' => '10'],
        ], $this->superAdmin());
    }

    public function test_group_accounts_take_no_postings(): void
    {
        $this->expectException(FinanceException::class);
        app(JournalService::class)->record(['branch_id' => $this->branchA->id, 'entry_date' => today(), 'source_type' => 'test'], [
            ['account' => $this->account('5000'), 'debit' => '10'],
            ['account' => $this->account('4100'), 'credit' => '10'],
        ], null);
    }
}
