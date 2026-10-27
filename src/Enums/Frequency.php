<?php

namespace SCMS\Finance\Enums;

use Carbon\CarbonInterface;

/** How often a member saves or repays. */
enum Frequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Flexible = 'flexible';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    /** Periods in a year, for converting a yearly rate (flexible counts as monthly). */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Daily => 365,
            self::Weekly => 52,
            self::Monthly, self::Flexible => 12,
        };
    }

    public function advance(CarbonInterface $date, int $times = 1): CarbonInterface
    {
        return match ($this) {
            self::Daily => $date->copy()->addDays($times),
            self::Weekly => $date->copy()->addWeeks($times),
            self::Monthly, self::Flexible => $date->copy()->addMonthsNoOverflow($times),
        };
    }

    /** @return list<self> */
    public static function repaymentCases(): array
    {
        return [self::Weekly, self::Monthly];
    }

    /** @param  list<self>|null  $cases
     *  @return array<string, string> */
    public static function options(?array $cases = null): array
    {
        return collect($cases ?? self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
