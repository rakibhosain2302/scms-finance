<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Enums\InterestMethod;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Models\Concerns\BelongsToMember;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Support\FinanceModels;
use SCMS\Finance\Support\Money;

/**
 * A loan from application to completion. The terms are copied from the product when applied.
 * principal_outstanding is a cache of the receivable sub-ledger.
 */
class Loan extends FinanceModel
{
    use BelongsToMember, BranchScoped, HasCreator;

    protected $table = 'finance_loans';

    protected $fillable = ['purpose'];

    protected array $money = ['principal', 'processing_fee', 'total_interest', 'principal_outstanding'];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => Frequency::class,
            'interest_rate' => 'decimal:4',
            'applied_on' => 'date',
            'disbursed_on' => 'date',
            'first_due_on' => 'date',
            'closed_on' => 'date',
            'written_off_on' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_no');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class)->withoutGlobalScope('finance_branch')->orderByDesc('repayment_date')->orderByDesc('id');
    }

    public function disbursementAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class, 'disbursement_account_id')->withoutGlobalScopes();
    }

    public function disbursementEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'disbursement_entry_id')->withoutGlobalScope('finance_branch');
    }

    public function writeOffEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'write_off_entry_id')->withoutGlobalScope('finance_branch');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(FinanceModels::user(), 'approved_by');
    }

    /** Principal plus interest of the schedule. */
    public function totalPayable(): Money
    {
        return $this->money('principal')->plus($this->total_interest);
    }
}
