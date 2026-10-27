<?php

namespace SCMS\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use SCMS\Finance\Support\Money;

/** @mixin \SCMS\Finance\Models\CashBankAccount */
class CashBankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'kind' => $this->kind->value,
            'name' => $this->name,
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'is_active' => $this->is_active,
            'balance' => Money::of($this->ledger_balance)->toString(),
        ];
    }
}
