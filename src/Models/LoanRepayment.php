<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Models\Concerns\HasEntryStatus;

/** A payment against a loan, split into principal, interest and penalty. */
class LoanRepayment extends FinanceModel
{
    use BranchScoped, HasCreator, HasEntryStatus;

    protected $table = 'finance_loan_repayments';

    protected $fillable = ['reference', 'description'];

    protected array $money = ['amount', 'principal_amount', 'interest_amount', 'penalty_amount'];

    protected function casts(): array
    {
        return ['repayment_date' => 'date'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withoutGlobalScope('finance_branch');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanRepaymentAllocation::class);
    }

    public function cashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class)->withoutGlobalScopes();
    }
}
