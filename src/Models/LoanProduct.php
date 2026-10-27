<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Enums\InterestMethod;
use SCMS\Finance\Models\Concerns\HasCreator;

/** Loan terms and the accounts loans of this product post to. */
class LoanProduct extends FinanceModel
{
    use HasCreator;

    protected $table = 'finance_loan_products';

    protected $fillable = [
        'code', 'name', 'interest_method', 'interest_rate', 'repayment_frequency', 'min_installments', 'max_installments',
        'min_amount', 'max_amount', 'processing_fee_rate', 'grace_days', 'receivable_account_id',
        'interest_income_account_id', 'penalty_income_account_id', 'fee_income_account_id', 'is_active', 'description',
    ];

    protected array $money = ['min_amount', 'max_amount'];

    protected function casts(): array
    {
        return [
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => Frequency::class,
            'interest_rate' => 'decimal:4',
            'processing_fee_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    public function interestIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'interest_income_account_id');
    }

    public function penaltyIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'penalty_income_account_id');
    }

    public function feeIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fee_income_account_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
