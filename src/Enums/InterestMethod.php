<?php

namespace SCMS\Finance\Enums;

enum InterestMethod: string
{
    // No interest or service charge.
    case None = 'none';
    // Interest on the full principal for the whole term, spread evenly.
    case Flat = 'flat';
    // Interest on the outstanding principal each period (equal installments).
    case Declining = 'declining';

    public function label(): string
    {
        return match ($this) {
            self::None => __('No interest'),
            self::Flat => __('Flat rate'),
            self::Declining => __('Declining balance'),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
