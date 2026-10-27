<?php

namespace SCMS\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\AccountType;

class AccountRequest extends FinanceRequest
{
    public function rules(): array
    {
        $id = $this->route('account')?->id;

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/', Rule::unique('finance_accounts', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'subtype' => ['required', Rule::enum(AccountSubtype::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('finance_accounts', 'id')->where('is_postable', false), Rule::notIn(array_filter([$id]))],
            'is_postable' => ['boolean'],
            'is_active' => ['boolean'],
            'branch_id' => ['nullable', 'integer', Rule::exists(config('scms-finance.tables.branches', 'branches'), 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
