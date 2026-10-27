<?php

namespace SCMS\Finance\Enums;

enum CashBankKind: string
{
    case Cash = 'cash';
    case Bank = 'bank';

    public function label(): string
    {
        return $this === self::Cash ? __('Cash') : __('Bank');
    }

    public function subtype(): AccountSubtype
    {
        return $this === self::Cash ? AccountSubtype::Cash : AccountSubtype::Bank;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
