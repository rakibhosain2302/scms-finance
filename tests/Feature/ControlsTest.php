<?php

namespace SCMS\Finance\Tests\Feature;

use Illuminate\Support\Facades\Date;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Services\FinancialYearService;
use SCMS\Finance\Services\IncomeService;
use SCMS\Finance\Services\JournalService;
use SCMS\Finance\Services\TransferService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Tests\FinanceTestCase;

/** Maker-checker on drafts, staff without a branch, and the business date. */
class ControlsTest extends FinanceTestCase
{
    private function income(): array
    {
        return ['branch_id' => $this->branchA->id, 'voucher_date' => today()->toDateString(), 'account_id' => $this->account('4100')->id,
            'cash_bank_account_id' => $this->cashA->id, 'amount' => '500'];
    }

    public function test_the_writer_of_a_voucher_cannot_post_it_but_someone_else_can(): void
    {
        $abilities = [Abilities::INCOME_VIEW, Abilities::INCOME_CREATE, Abilities::INCOME_POST];
        $maker = $this->makeUser($abilities, [$this->branchA->id]);
        $checker = $this->makeUser($abilities, [$this->branchA->id]);
        $service = app(IncomeService::class);

        $draft = $service->create($this->income(), null, false, $maker);
        $this->assertFalse($maker->can('post', $draft));
        $this->assertTrue($checker->can('post', $draft));

        try {
            $service->post($draft, $maker);
            $this->fail('The writer posted their own voucher.');
        } catch (FinanceException) {
            $this->assertSame(EntryStatus::Draft, $draft->refresh()->status);
        }

        $this->assertSame(EntryStatus::Posted, $service->post($draft, $checker)->status);
    }

    public function test_save_and_post_is_refused_to_staff_and_allowed_to_super_admins(): void
    {
        $maker = $this->makeUser([Abilities::INCOME_VIEW, Abilities::INCOME_CREATE, Abilities::INCOME_POST], [$this->branchA->id]);

        $this->actingAs($maker)->post(route('finance.incomes.store'), $this->income() + ['post' => '1'])->assertRedirect();
        $this->assertSame(EntryStatus::Draft, \SCMS\Finance\Models\Income::query()->withoutBranchScope()->latest('id')->firstOrFail()->status);

        $posted = app(IncomeService::class)->create($this->income(), null, true, $this->superAdmin());
        $this->assertSame(EntryStatus::Posted, $posted->status);
    }

    public function test_transfers_and_manual_journals_follow_the_same_rule(): void
    {
        $maker = $this->makeUser([], [$this->branchA->id]);
        $transfer = app(TransferService::class)->create(['branch_id' => $this->branchA->id, 'transfer_date' => today()->toDateString(),
            'from_account_id' => $this->cashA->id, 'to_account_id' => $this->bankA->id, 'amount' => '100'], false, $maker);
        $journal = app(JournalService::class)->createDraft(['branch_id' => $this->branchA->id, 'entry_date' => today()->toDateString()], [
            ['account_id' => $this->account('5100')->id, 'debit' => '10'], ['account_id' => $this->account('4100')->id, 'credit' => '10'],
        ], $maker);

        foreach ([fn () => app(TransferService::class)->post($transfer, $maker), fn () => app(JournalService::class)->post($journal, $maker)] as $post) {
            try {
                $post();
                $this->fail('The writer posted their own draft.');
            } catch (FinanceException $e) {
                $this->assertStringContainsString('must post it', $e->getMessage());
            }
        }

        $checker = $this->makeUser([], [$this->branchA->id]);
        $this->assertSame(EntryStatus::Posted, app(TransferService::class)->post($transfer, $checker)->status);
        $this->assertSame(EntryStatus::Posted, app(JournalService::class)->post($journal, $checker)->status);
    }

    public function test_self_posting_can_be_allowed_in_config(): void
    {
        config(['scms-finance.rules.self_posting' => true]);
        $maker = $this->makeUser([], [$this->branchA->id]);
        $draft = app(IncomeService::class)->create($this->income(), null, false, $maker);

        $this->assertSame(EntryStatus::Posted, app(IncomeService::class)->post($draft, $maker)->status);
    }

    public function test_staff_without_a_branch_see_and_touch_nothing(): void
    {
        $account = $this->openSavings();
        $nobody = $this->makeUser([Abilities::SAVINGS_VIEW, Abilities::SAVINGS_DEPOSIT, Abilities::REPORT_VIEW], []);

        $this->actingAs($nobody)->get(route('finance.savings-accounts.show', $account))->assertNotFound();
        $this->actingAs($nobody)->get(route('finance.savings-accounts.index'))->assertOk()->assertDontSee($account->account_number);
        $this->assertSame(0, SavingsAccount::query()->count());

        $this->expectException(BranchAccessException::class);
        app(JournalService::class)->assertBranch($nobody, $this->branchA->id);
    }

    public function test_without_the_rule_staff_without_a_branch_follow_nit_master(): void
    {
        config(['scms-finance.rules.require_branch' => false]);
        $account = $this->openSavings();
        $nobody = $this->makeUser([Abilities::SAVINGS_VIEW], []);

        $this->actingAs($nobody)->get(route('finance.savings-accounts.show', $account))->assertOk();
    }

    public function test_the_business_date_follows_the_app_timezone(): void
    {
        // 00:30 in Dhaka is still the previous day in UTC: "today" must be Dhaka's date.
        config(['app.timezone' => 'Asia/Dhaka']);
        date_default_timezone_set('Asia/Dhaka');
        Date::setTestNow(Date::parse('2026-10-28 00:30:00', 'Asia/Dhaka'));

        try {
            app(FinancialYearService::class)->assertPostingDate('2026-10-28');
            $this->assertSame('2026-10-28', today()->toDateString());
        } finally {
            Date::setTestNow();
            date_default_timezone_set(config('app.timezone'));
        }
    }
}
