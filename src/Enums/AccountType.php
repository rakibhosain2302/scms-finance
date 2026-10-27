<?php

namespace SCMS\Finance\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    /** Assets and expenses grow with debits; the rest with credits. */
    public function isDebitNormal(): bool
    {
        return in_array($this, [self::Asset, self::Expense], true);
    }

    /** Balance sheet accounts carry their balance into the next year; income and expense start at zero. */
    public function isBalanceSheet(): bool
    {
        return ! in_array($this, [self::Income, self::Expense], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
