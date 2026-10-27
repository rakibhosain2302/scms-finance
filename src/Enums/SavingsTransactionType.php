<?php

namespace SCMS\Finance\Enums;

enum SavingsTransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    // Credited by SavingsInterestService; moves no cash.
    case Interest = 'interest';
    // A balance brought in when the account was opened (go-live); moves no cash.
    case Opening = 'opening';

    public function label(): string
    {
        return $this === self::Opening ? __('Opening balance') : __(ucfirst($this->value));
    }

    public function numberKey(): string
    {
        return match ($this) {
            self::Deposit => 'savings_deposit',
            self::Withdrawal => 'savings_withdrawal',
            self::Interest => 'savings_interest',
            self::Opening => 'opening_balance',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
