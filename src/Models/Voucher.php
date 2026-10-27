<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use SCMS\Finance\Models\Concerns\BranchScoped;
use SCMS\Finance\Models\Concerns\HasCreator;
use SCMS\Finance\Models\Concerns\HasEntryStatus;

/** An income or expense voucher: drafted, posted, reversed. */
abstract class Voucher extends FinanceModel
{
    use BranchScoped, HasCreator, HasEntryStatus;

    protected array $money = ['amount'];

    /** "payer" or "payee". */
    abstract public static function partyColumn(): string;

    /** "income" or "expense": the number series, the routes and the abilities. */
    abstract public static function kind(): string;

    protected function casts(): array
    {
        return ['voucher_date' => 'date'];
    }

    public function getFillable()
    {
        return ['voucher_date', 'account_id', 'cash_bank_account_id', 'amount', static::partyColumn(), 'reference', 'description'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function cashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class)->withoutGlobalScopes();
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? Storage::disk(config('scms-finance.attachments.disk', 'public'))->url($this->attachment_path) : null;
    }
}
