<?php

namespace SCMS\Finance\Listeners;

use Illuminate\Database\Eloquent\Model;
use SCMS\Finance\Events\DepositPosted;
use SCMS\Finance\Events\FinanceEvent;
use SCMS\Finance\Events\LoanDisbursed;
use SCMS\Finance\Events\LoanRepaymentPosted;
use SCMS\Finance\Events\WithdrawalPosted;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Support\FinanceModels;
use SCMS\Finance\Support\Money;
use Throwable;

/**
 * Tells the member by SMS about money moved on their savings or loan (scms-finance.sms.notify),
 * through nit-master's SMS gateway. A failed message never undoes the posting: it is only reported.
 */
class SendMemberSms
{
    public function handle(FinanceEvent $event): void
    {
        if (! config('scms-finance.sms.notify') || ! interface_exists(\Nit\Master\Contracts\SmsSender::class)) {
            return;
        }

        try {
            [$memberId, $message] = $this->message($event);
            $phone = $memberId ? self::phone($memberId) : null;
            if ($phone && $message) {
                app(\Nit\Master\Contracts\SmsSender::class)->send($phone, $message);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function phone(int $memberId): ?string
    {
        $model = FinanceModels::member();
        $column = config('scms-finance.member_columns.phone');
        $member = $model && $column ? $model::query()->withoutGlobalScopes()->find($memberId) : null;

        return $member ? (trim((string) $member->getAttribute($column)) ?: null) : null;
    }

    /** @return array{0: ?int, 1: ?string} */
    private function message(FinanceEvent $event): array
    {
        $s = $event->subject;
        $app = config('app.name');

        return match (true) {
            $event instanceof DepositPosted, $event instanceof WithdrawalPosted => [(int) $s->savingsAccount->member_id, __(':app: :type of :amount on :account. Balance :balance. Ref :number', [
                'app' => $app, 'type' => $s->type->label(), 'amount' => Money::of($s->amount)->format(), 'account' => $s->savingsAccount->account_number,
                'balance' => app(LedgerService::class)->savingsBalance($s->savings_account_id)->format(), 'number' => $s->number,
            ])],
            $event instanceof LoanRepaymentPosted => [(int) $s->loan->member_id, __(':app: repayment of :amount received on loan :loan. Still owed :owed. Ref :number', [
                'app' => $app, 'amount' => Money::of($s->amount)->format(), 'loan' => $s->loan->loan_number,
                'owed' => app(LedgerService::class)->loanOutstanding($s->loan_id)->format(), 'number' => $s->number,
            ])],
            $event instanceof LoanDisbursed => [(int) $s->member_id, __(':app: loan :loan of :amount disbursed. First installment due :date.', [
                'app' => $app, 'loan' => $s->loan_number, 'amount' => Money::of($s->principal)->format(), 'date' => $s->first_due_on?->format('j M Y'),
            ])],
            default => [null, null],
        };
    }
}
