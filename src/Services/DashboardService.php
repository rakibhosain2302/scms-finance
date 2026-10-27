<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Support\Money;

/**
 * Figures for a finance dashboard, from the ledger. Reusable by the app's own dashboard
 * (e.g. a nit-master DashboardWidgets widget): app(DashboardService::class)->summary($user).
 */
class DashboardService
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * @return array{today: array<string, Money>, cash: Money, bank: Money, savings: Money, loans: Money, overdue: Money, overdue_loans: int}
     */
    public function summary(?Authenticatable $user, int|string|null $branchId = null): array
    {
        $branchIds = $this->reports->branchScope($user, $branchId);
        $balances = $this->reports->balancesByBranch($branchIds);
        $sum = fn (string $key) => Money::sum(array_column($balances, $key));
        $portfolio = $this->reports->loanPortfolio($branchIds);

        return [
            'today' => $this->reports->activity($branchIds, today(), today()),
            'cash' => $sum('cash'),
            'bank' => $sum('bank'),
            'savings' => $sum('savings'),
            'loans' => $sum('loans'),
            'overdue' => $portfolio['overdue'],
            'overdue_loans' => $portfolio['overdue_loans'],
        ];
    }
}
