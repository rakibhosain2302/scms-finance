<?php

namespace SCMS\Finance\Enums;

/**
 * What an account is used for. Screens offer accounts by sub-type (an expense form lists
 * expense accounts, a cash account must point at a "cash" account) and reports group by it.
 */
enum AccountSubtype: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case LoanReceivable = 'loan_receivable';
    case MemberSavings = 'member_savings';
    case Equity = 'equity';
    case InterestIncome = 'interest_income';
    case FeeIncome = 'fee_income';
    case PenaltyIncome = 'penalty_income';
    case General = 'general';

    public function label(): string
    {
        return __(ucwords(str_replace('_', ' ', $this->value)));
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
