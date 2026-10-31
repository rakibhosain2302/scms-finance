<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Support\FinanceModels;

/** Dividend of one closed financial year, credited to members' savings accounts of one scheme. */
class DividendRun extends FinanceModel
{
    use HasCreator;

    protected $table = 'finance_dividend_runs';

    protected $fillable = [];

    protected array $money = ['total'];

    protected function casts(): array
    {
        return ['posting_date' => 'date', 'rate' => 'decimal:4'];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(FinanceModels::financialYear(), 'financial_year_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(SavingsScheme::class, 'savings_scheme_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class)->withoutGlobalScope('finance_branch');
    }
}
