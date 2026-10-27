<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;

/** Interest of one scheme in one branch for a period, credited to every account that earned it. */
class SavingsInterestRun extends FinanceModel
{
    use BranchScoped, HasCreator;

    protected $table = 'finance_savings_interest_runs';

    protected $fillable = [];

    protected array $money = ['total'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'posting_date' => 'date', 'rate' => 'decimal:4'];
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
