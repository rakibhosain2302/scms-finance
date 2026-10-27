<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Models\Concerns\HasEntryStatus;

/** Money moved between two cash or bank accounts of a branch: never income or expense. */
class Transfer extends FinanceModel
{
    use BranchScoped, HasCreator, HasEntryStatus;

    protected $table = 'finance_transfers';

    protected $fillable = ['transfer_date', 'from_account_id', 'to_account_id', 'amount', 'reference', 'description'];

    protected array $money = ['amount'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date'];
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class, 'from_account_id')->withoutGlobalScopes();
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class, 'to_account_id')->withoutGlobalScopes();
    }
}
