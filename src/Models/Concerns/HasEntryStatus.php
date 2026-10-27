<?php

namespace SCMS\Finance\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Models\JournalEntry;
use SCMS\Finance\Support\FinanceModels;

/**
 * Documents that post to the ledger (deposits, repayments, vouchers, transfers):
 * status, their journal entry and the entry that reversed them.
 */
trait HasEntryStatus
{
    public function initializeHasEntryStatus(): void
    {
        $this->mergeCasts(['status' => EntryStatus::class, 'posted_at' => 'datetime']);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id')->withoutGlobalScope('finance_branch');
    }

    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_entry_id')->withoutGlobalScope('finance_branch');
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

    public function scopePosted(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), EntryStatus::Posted->value);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where($query->qualifyColumn('status'), $status) : $query;
    }
}
