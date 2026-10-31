<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Models\Concerns\HasEntryStatus;

/** Money received on a written-off loan: income, since the loss was already booked. */
class LoanRecovery extends FinanceModel
{
    use BranchScoped, HasCreator, HasEntryStatus;

    protected $table = 'finance_loan_recoveries';

    protected $fillable = ['reference'];

    protected array $money = ['amount'];

    protected function casts(): array
    {
        return ['recovery_date' => 'date'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withoutGlobalScopes();
    }

    public function cashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class)->withoutGlobalScopes();
    }
}
