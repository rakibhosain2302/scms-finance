<?php

namespace SCMS\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use SCMS\Finance\Support\Money;

/** @mixin \SCMS\Finance\Models\Loan */
class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_number' => $this->loan_number,
            'branch_id' => $this->branch_id,
            'member_id' => $this->member_id,
            'product' => $this->whenLoaded('product', fn () => ['id' => $this->product->id, 'code' => $this->product->code, 'name' => $this->product->name]),
            'status' => $this->status->value,
            'principal' => $this->principal,
            'interest_method' => $this->interest_method->value,
            'interest_rate' => $this->interest_rate,
            'installments_count' => $this->installments_count,
            'total_interest' => $this->total_interest,
            'applied_on' => $this->applied_on?->toDateString(),
            'disbursed_on' => $this->disbursed_on?->toDateString(),
            'outstanding_principal' => Money::of($this->ledger_outstanding)->toString(),
            'installments' => $this->whenLoaded('installments', fn () => $this->installments->map(fn ($i) => [
                'no' => $i->installment_no,
                'due_date' => $i->due_date->toDateString(),
                'principal_due' => $i->principal_due,
                'interest_due' => $i->interest_due,
                'principal_paid' => $i->principal_paid,
                'interest_paid' => $i->interest_paid,
                'status' => $i->status->value,
                'overdue' => $i->isOverdue(),
            ])),
        ];
    }
}
