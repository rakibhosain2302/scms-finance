<?php

namespace SCMS\Finance\Console;

use Illuminate\Console\Command;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Listeners\SendMemberSms;
use SCMS\Finance\Models\LoanInstallment;
use Throwable;

/** SMS to members whose installment falls due in a few days (schedule it daily). */
class LoanRemindersCommand extends Command
{
    protected $signature = 'finance:loan-reminders {--days= : Days ahead (default scms-finance.sms.reminder_days)}';

    protected $description = 'Remind members by SMS of loan installments falling due soon';

    public function handle(): int
    {
        if (! interface_exists(\Nit\Master\Contracts\SmsSender::class)) {
            $this->components->warn('No SMS gateway (nit-master) is installed.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?? config('scms-finance.sms.reminder_days', 2));
        $due = today()->addDays($days);
        $installments = LoanInstallment::query()->unpaid()->whereDate('due_date', $due->toDateString())
            ->whereHas('loan', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', LoanStatus::runningValues()))
            ->with(['loan' => fn ($q) => $q->withoutGlobalScopes()])->get();

        $sent = 0;
        foreach ($installments as $installment) {
            $phone = SendMemberSms::phone((int) $installment->loan->member_id);
            if (! $phone) {
                continue;
            }
            try {
                app(\Nit\Master\Contracts\SmsSender::class)->send($phone, __(':app: installment :n of loan :loan, :amount, is due on :date.', [
                    'app' => config('app.name'), 'n' => $installment->installment_no, 'loan' => $installment->loan->loan_number,
                    'amount' => $installment->remaining()->format(), 'date' => $due->format('j M Y'),
                ]));
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->components->info("{$sent} reminders sent for installments due on {$due->toDateString()}.");

        return self::SUCCESS;
    }
}
