<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Models\Concerns\HasCreator;

class SavingsScheme extends FinanceModel
{
    use HasCreator;

    protected $table = 'finance_savings_schemes';

    protected $fillable = [
        'code', 'name', 'frequency', 'min_deposit', 'max_deposit', 'minimum_balance', 'allow_withdrawal',
        'max_withdrawals_per_month', 'term_months', 'withdraw_before_maturity', 'interest_rate', 'liability_account_id', 'is_active', 'description',
    ];

    protected array $money = ['min_deposit', 'max_deposit', 'minimum_balance'];

    protected function casts(): array
    {
        return ['frequency' => Frequency::class, 'allow_withdrawal' => 'boolean', 'withdraw_before_maturity' => 'boolean', 'is_active' => 'boolean', 'interest_rate' => 'decimal:4'];
    }

    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
