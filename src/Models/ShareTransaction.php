<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Models\Concerns\BelongsToMember;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Models\Concerns\HasEntryStatus;

/** A member buying shares (purchase) or getting share money back (refund). Posted at once; corrected by reversal. */
class ShareTransaction extends FinanceModel
{
    use BelongsToMember, BranchScoped, HasCreator, HasEntryStatus;

    public const PURCHASE = 'purchase';

    public const REFUND = 'refund';

    protected $table = 'finance_share_transactions';

    protected $fillable = ['reference', 'description'];

    protected array $money = ['amount'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date'];
    }

    public function cashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class)->withoutGlobalScopes();
    }

    public function typeLabel(): string
    {
        return $this->type === self::REFUND ? __('Share refund') : __('Share purchase');
    }
}
