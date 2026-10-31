<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use SCMS\Finance\Enums\InstallmentStatus;
use SCMS\Finance\Support\Money;

class LoanInstallment extends FinanceModel
{
    protected $table = 'finance_loan_installments';

    protected $fillable = [];

    protected array $money = ['principal_due', 'interest_due', 'principal_paid', 'interest_paid', 'interest_waived', 'penalty_charged'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_on' => 'date', 'status' => InstallmentStatus::class];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withoutGlobalScope('finance_branch');
    }

    public function totalDue(): Money
    {
        return $this->money('principal_due')->plus($this->interest_due);
    }

    public function totalPaid(): Money
    {
        return $this->money('principal_paid')->plus($this->interest_paid);
    }

    public function remaining(): Money
    {
        return $this->totalDue()->minus($this->totalPaid());
    }

    public function principalRemaining(): Money
    {
        return $this->money('principal_due')->minus($this->principal_paid);
    }

    public function interestRemaining(): Money
    {
        return $this->money('interest_due')->minus($this->interest_paid);
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->status !== InstallmentStatus::Paid && $this->due_date->lt(($today ?? today())->startOfDay());
    }

    /** Not fully paid and due before the date (today by default). */
    public function scopeOverdue(Builder $query, ?Carbon $asOf = null): Builder
    {
        return $query->where($query->qualifyColumn('status'), '!=', InstallmentStatus::Paid->value)
            ->whereDate($query->qualifyColumn('due_date'), '<', ($asOf ?? today())->toDateString());
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), '!=', InstallmentStatus::Paid->value);
    }
}
