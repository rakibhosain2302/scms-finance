<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Support\FinanceModels;
use SCMS\Finance\Support\Money;

/**
 * A journal entry. Written only by JournalService: posted entries never change except for
 * the status "reversed" and the reversal fields.
 */
class JournalEntry extends FinanceModel
{
    use BranchScoped, HasCreator;

    protected $table = 'finance_journal_entries';

    protected $fillable = ['entry_date', 'description', 'reference'];

    protected array $money = ['total'];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => EntryStatus::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinanceModels::financialYear(), 'financial_year_id');
    }

    /** The entry this one reverses. */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id')->withoutGlobalScope('finance_branch');
    }

    /** The entry that reversed this one. */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id')->withoutGlobalScope('finance_branch');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(FinanceModels::user(), 'posted_by');
    }

    public function isDraft(): bool
    {
        return $this->status === EntryStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === EntryStatus::Posted;
    }

    /** Draft entries written by hand; entries of documents change with their document. */
    public function isManual(): bool
    {
        return $this->source_type === 'manual';
    }

    public function totalDebit(): Money
    {
        return Money::sum($this->lines->pluck('debit'));
    }

    public function totalCredit(): Money
    {
        return Money::sum($this->lines->pluck('credit'));
    }

    /** In the ledger: posted, including entries reversed later (their reversal is in the ledger too). */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), [EntryStatus::Posted->value, EntryStatus::Reversed->value]);
    }
}
