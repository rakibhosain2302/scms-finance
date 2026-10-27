<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Enums\SavingsTransactionType;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Models\Concerns\HasEntryStatus;

/** A deposit or withdrawal. Posted at once; corrected by reversal. */
class SavingsTransaction extends FinanceModel
{
    use BranchScoped, HasCreator, HasEntryStatus;

    protected $table = 'finance_savings_transactions';

    protected $fillable = ['reference', 'description'];

    protected array $money = ['amount'];

    protected function casts(): array
    {
        return ['type' => SavingsTransactionType::class, 'transaction_date' => 'date'];
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class)->withoutGlobalScope('finance_branch');
    }

    public function cashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class)->withoutGlobalScopes();
    }
}
