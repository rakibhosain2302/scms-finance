<?php

namespace SCMS\Finance\Enums;

/** How a loan product charges for installments paid late (after its grace days). */
enum PenaltyType: string
{
    // The cashier enters any penalty (or none) at the repayment.
    case None = 'none';
    // A fixed amount per overdue installment, once.
    case Fixed = 'fixed';
    // A percentage of what is still unpaid on each overdue installment, once.
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::None => __('No rule (entered by hand)'),
            self::Fixed => __('Fixed amount per late installment'),
            self::Percent => __('Percent of the late installment'),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
