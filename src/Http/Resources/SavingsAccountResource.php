<?php

namespace SCMS\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use SCMS\Finance\Support\Money;

/** @mixin \SCMS\Finance\Models\SavingsAccount */
class SavingsAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'branch_id' => $this->branch_id,
            'member_id' => $this->member_id,
            'scheme' => $this->whenLoaded('scheme', fn () => ['id' => $this->scheme->id, 'code' => $this->scheme->code, 'name' => $this->scheme->name]),
            'status' => $this->status->value,
            'opened_on' => $this->opened_on?->toDateString(),
            'closed_on' => $this->closed_on?->toDateString(),
            // From the ledger, not the cached column.
            'balance' => Money::of($this->ledger_balance)->toString(),
        ];
    }
}
