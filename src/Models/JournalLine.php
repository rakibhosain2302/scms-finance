<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Enums\LineComponent;

class JournalLine extends FinanceModel
{
    protected $table = 'finance_journal_lines';

    protected $fillable = [
        'line_no', 'account_id', 'debit', 'credit', 'description', 'component',
        'cash_bank_account_id', 'member_id', 'savings_account_id', 'loan_id',
    ];

    protected array $money = ['debit', 'credit'];

    protected function casts(): array
    {
        return ['component' => LineComponent::class];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id')->withoutGlobalScope('finance_branch');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function cashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class, 'cash_bank_account_id')->withoutGlobalScopes();
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id')->withoutGlobalScopes();
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id')->withoutGlobalScopes();
    }
}
