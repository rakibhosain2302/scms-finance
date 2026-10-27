<?php

namespace SCMS\Finance\Support;

use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Enums\InterestMethod;

/**
 * Installment schedules. Pure arithmetic, no database.
 *
 * - none: the principal in equal parts.
 * - flat: interest = principal × yearly rate × term in years, spread in equal parts.
 * - declining: equal installments; each period's interest is on the principal still owed.
 *
 * Parts are rounded down to the money scale; the last installment takes the remainder, so the
 * schedule always adds up to the exact principal (and the exact flat interest).
 */
class LoanScheduleCalculator
{
    /**
     * @return list<array{installment_no: int, due_date: CarbonInterface, principal: Money, interest: Money}>
     */
    public function build(Money|string $principal, InterestMethod $method, string|int $annualRate, int $count, Frequency $frequency, CarbonInterface $firstDue): array
    {
        $principal = Money::of($principal);
        if (! $principal->isPositive()) {
            throw new InvalidArgumentException('The principal must be more than zero.');
        }
        if ($count < 1) {
            throw new InvalidArgumentException('At least one installment is needed.');
        }

        $rate = BigDecimal::of((string) $annualRate);
        if ($rate->isNegative()) {
            throw new InvalidArgumentException('The rate cannot be negative.');
        }

        $parts = match (true) {
            $method === InterestMethod::None, $rate->isZero() => $this->even($principal, Money::zero(), $count),
            $method === InterestMethod::Flat => $this->even($principal, $this->flatInterest($principal, $rate, $count, $frequency), $count),
            default => $this->declining($principal, $rate, $count, $frequency),
        };

        $schedule = [];
        foreach ($parts as $i => [$p, $int]) {
            $schedule[] = [
                'installment_no' => $i + 1,
                'due_date' => $frequency->advance($firstDue, $i),
                'principal' => $p,
                'interest' => $int,
            ];
        }

        return $schedule;
    }

    /** Total interest of a schedule. */
    public function totalInterest(array $schedule): Money
    {
        return Money::sum(array_column($schedule, 'interest'));
    }

    public function flatInterest(Money $principal, BigDecimal $rate, int $count, Frequency $frequency): Money
    {
        // principal × rate% × (count / periods a year), computed exactly and rounded once.
        $exact = BigDecimal::of($principal->toString())->multipliedBy($rate)->multipliedBy($count)
            ->dividedBy(100 * $frequency->periodsPerYear(), 12, Money::halfUp());

        return Money::of($exact);
    }

    /** @return list<array{0: Money, 1: Money}> */
    private function even(Money $principal, Money $interest, int $count): array
    {
        $p = $principal->dividedDown($count);
        $i = $interest->dividedDown($count);
        $parts = array_fill(0, $count - 1, [$p, $i]);
        $parts[] = [$principal->minus($p->times($count - 1)), $interest->minus($i->times($count - 1))];

        return $parts;
    }

    /** @return list<array{0: Money, 1: Money}> */
    private function declining(Money $principal, BigDecimal $rate, int $count, Frequency $frequency): array
    {
        $scale = 20;
        $r = $rate->dividedBy(100 * $frequency->periodsPerYear(), $scale, Money::halfUp());
        $growth = BigDecimal::one()->plus($r)->power($count)->toScale($scale, Money::halfUp());
        // Installment = P · r · (1 + r)^n / ((1 + r)^n − 1)
        $installment = Money::of(
            BigDecimal::of($principal->toString())->multipliedBy($r)->multipliedBy($growth)
                ->dividedBy($growth->minus(BigDecimal::one()), $scale, Money::halfUp())
        );

        $parts = [];
        $owed = $principal;
        for ($i = 1; $i <= $count; $i++) {
            $interest = Money::of(BigDecimal::of($owed->toString())->multipliedBy($r));
            $p = $i === $count ? $owed : Money::min(Money::max($installment->minus($interest), 0), $owed);
            $parts[] = [$p, $interest];
            $owed = $owed->minus($p);
        }

        return $parts;
    }
}
