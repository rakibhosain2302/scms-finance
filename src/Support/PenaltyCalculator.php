<?php

namespace SCMS\Finance\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SCMS\Finance\Enums\PenaltyType;
use SCMS\Finance\Models\LoanInstallment;
use SCMS\Finance\Models\LoanProduct;

/**
 * Penalty a loan owes under its product's rule: once per installment still unpaid more than the
 * grace days after it fell due, less what was already charged on that installment.
 */
final class PenaltyCalculator
{
    /**
     * @param  iterable<LoanInstallment>  $installments  the loan's unpaid installments, oldest first
     * @return Collection<int, Money> installment id => penalty still owed (only positive amounts)
     */
    public function due(LoanProduct $product, iterable $installments, Carbon $asOf): Collection
    {
        $type = $product->penalty_type ?? PenaltyType::None;
        if ($type === PenaltyType::None) {
            return collect();
        }

        $late = $asOf->copy()->startOfDay()->subDays((int) $product->grace_days);
        $owed = collect();
        foreach ($installments as $installment) {
            if (! $installment->due_date->lt($late) || $installment->remaining()->isZero()) {
                continue;
            }

            $rule = $type === PenaltyType::Fixed
                ? Money::of((string) $product->penalty_value)
                : $installment->remaining()->times((string) $product->penalty_value)->dividedBy(100);
            $left = $rule->minus($installment->penalty_charged);

            if ($left->isPositive()) {
                $owed[$installment->id] = $left;
            }
        }

        return $owed;
    }
}
