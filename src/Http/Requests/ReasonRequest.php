<?php

namespace SCMS\Finance\Http\Requests;

use Illuminate\Support\Str;
use SCMS\Finance\Support\Abilities;

/** Reversals, rejections and other decisions need a reason, kept with the record. */
class ReasonRequest extends FinanceRequest
{
    /** Route name (without the "finance." prefix) => the ability it needs before validation. */
    private const ABILITIES = [
        'loans.reject' => Abilities::LOAN_APPROVE,
        'loans.write-off' => Abilities::LOAN_WRITE_OFF,
        'loans.reschedule' => Abilities::LOAN_RESCHEDULE,
        'loans.waive' => Abilities::LOAN_WAIVE,
        'loans.reverse-disbursement' => Abilities::REVERSE,
    ];

    protected function ability(): ?string
    {
        $name = Str::after((string) $this->route()?->getName(), (string) config('scms-finance.routes.name', 'finance.'));

        // Every "….reverse" route needs the reversal ability; cancelling a loan is decided by its policy.
        return self::ABILITIES[$name] ?? (Str::endsWith($name, '.reverse') ? Abilities::REVERSE : null);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}
