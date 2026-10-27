<?php

namespace SCMS\Finance\Support;

/**
 * Splits a payment over the unpaid installments, oldest first; inside each installment in the
 * configured order (interest before principal by default). Pure arithmetic, no database.
 * Never pays more than an installment still owes, so nothing can go negative; whatever is left
 * over is returned and the caller refuses the payment.
 */
class RepaymentAllocator
{
    /**
     * @param  iterable<array{id: int, principal_remaining: Money|string, interest_remaining: Money|string}>  $installments  oldest first
     * @param  list<'interest'|'principal'>  $order
     * @return array{allocations: list<array{id: int, principal: Money, interest: Money}>, principal: Money, interest: Money, leftover: Money}
     */
    public function allocate(iterable $installments, Money|string $amount, array $order = ['interest', 'principal']): array
    {
        $left = Money::of($amount);
        $allocations = [];
        $totals = ['principal' => Money::zero(), 'interest' => Money::zero()];

        foreach ($installments as $installment) {
            if (! $left->isPositive()) {
                break;
            }

            $paid = ['principal' => Money::zero(), 'interest' => Money::zero()];
            foreach ($order as $part) {
                $owed = Money::max($installment[$part.'_remaining'], 0);
                $take = Money::min($owed, $left);
                $paid[$part] = $paid[$part]->plus($take);
                $left = $left->minus($take);
            }

            if ($paid['principal']->isPositive() || $paid['interest']->isPositive()) {
                $allocations[] = ['id' => $installment['id'], 'principal' => $paid['principal'], 'interest' => $paid['interest']];
                $totals['principal'] = $totals['principal']->plus($paid['principal']);
                $totals['interest'] = $totals['interest']->plus($paid['interest']);
            }
        }

        return ['allocations' => $allocations, 'principal' => $totals['principal'], 'interest' => $totals['interest'], 'leftover' => $left];
    }
}
