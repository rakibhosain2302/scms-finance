<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepaymentAllocation extends FinanceModel
{
    protected $table = 'finance_loan_repayment_allocations';

    protected $fillable = ['loan_installment_id', 'principal', 'interest'];

    protected array $money = ['principal', 'interest'];

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(LoanRepayment::class, 'loan_repayment_id')->withoutGlobalScope('finance_branch');
    }
}
