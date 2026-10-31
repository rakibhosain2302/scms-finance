<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Enums\SavingsAccountStatus;
use SCMS\Finance\Models\Concerns\BelongsToMember;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;

/** A member's savings account. "balance" is a cache of the posted ledger. */
class SavingsAccount extends FinanceModel
{
    use BelongsToMember, BranchScoped, HasCreator;

    protected $table = 'finance_savings_accounts';

    protected $fillable = ['note'];

    protected array $money = ['balance'];

    protected function casts(): array
    {
        return ['status' => SavingsAccountStatus::class, 'opened_on' => 'date', 'closed_on' => 'date', 'maturity_date' => 'date'];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(SavingsScheme::class, 'savings_scheme_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class)->withoutGlobalScope('finance_branch');
    }

    public function isActive(): bool
    {
        return $this->status === SavingsAccountStatus::Active;
    }
}
