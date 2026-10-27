<?php

namespace SCMS\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SCMS\Finance\Contracts\BranchAccess;
use SCMS\Finance\Support\Money;

/**
 * Validation only; the controllers authorize. Ids sent by the browser (branch, account, member)
 * are checked here for shape and again by the services for ownership: never trusted as sent.
 */
abstract class FinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** One of the branches the user may pick. */
    protected function branchRule(): array
    {
        return ['required', 'integer', Rule::in(array_keys(app(BranchAccess::class)->options($this->user())))];
    }

    protected function moneyRule(bool $required = true, bool $positive = true): array
    {
        return [$required ? 'required' : 'nullable', 'decimal:0,'.Money::scale(), $positive ? 'gt:0' : 'gte:0', 'max:9999999999999'];
    }

    protected function rateRule(): array
    {
        return ['nullable', 'decimal:0,4', 'gte:0', 'max:1000'];
    }

    public function messages(): array
    {
        return ['branch_id.in' => __('Choose one of your branches.')];
    }
}
