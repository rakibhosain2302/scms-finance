<?php

namespace SCMS\Finance\Enums;

enum SavingsTransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    public function numberKey(): string
    {
        return $this === self::Deposit ? 'savings_deposit' : 'savings_withdrawal';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
