<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone who stands surety for a loan: a member (member_id) or anyone else. */
class LoanGuarantor extends FinanceModel
{
    protected $table = 'finance_loan_guarantors';

    protected $fillable = ['member_id', 'name', 'relation', 'phone', 'nid'];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withoutGlobalScopes();
    }
}
