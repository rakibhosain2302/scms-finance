<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Support\FinanceModels;

/** A bank (or cash) statement and the ledger lines cleared against it. draft → completed. */
class Reconciliation extends FinanceModel
{
    use BranchScoped, HasCreator;

    public const DRAFT = 'draft';

    public const COMPLETED = 'completed';

    protected $table = 'finance_reconciliations';

    protected $fillable = ['note'];

    protected array $money = ['statement_balance', 'cleared_balance', 'book_balance'];

    protected function casts(): array
    {
        return ['statement_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function cashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class)->withoutGlobalScopes();
    }

    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(JournalLine::class, 'finance_reconciliation_lines', 'reconciliation_id', 'journal_line_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(FinanceModels::user(), 'completed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }
}
