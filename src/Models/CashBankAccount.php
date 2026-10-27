<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Enums\CashBankKind;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;

/** A cash drawer or bank account of one branch; its balance comes from the ledger only. */
class CashBankAccount extends FinanceModel
{
    use BranchScoped, HasCreator;

    protected $table = 'finance_cash_bank_accounts';

    protected $fillable = ['name', 'bank_name', 'bank_branch', 'account_number', 'is_active', 'description'];

    protected function casts(): array
    {
        return ['kind' => CashBankKind::class, 'is_active' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'cash_bank_account_id');
    }

    public function label(): string
    {
        return $this->kind === CashBankKind::Bank && $this->account_number
            ? $this->name.' ('.$this->account_number.')'
            : $this->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Active accounts of a branch for a select, as id => label.
     *
     * @return array<int, string>
     */
    public static function options(?int $branchId, ?CashBankKind $kind = null): array
    {
        return static::query()->active()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($kind, fn ($q) => $q->where('kind', $kind->value))
            ->orderBy('kind')->orderBy('name')->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->kind->label().' · '.$a->label()])->all();
    }
}
