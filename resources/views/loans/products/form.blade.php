@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $product->exists ? __('Edit :name', ['name' => $product->name]) : __('Add loan product'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    <form method="POST" action="{{ $product->exists ? route('finance.loan-products.update', $product) : route('finance.loan-products.store') }}" data-nx-once novalidate>
        @csrf
        @if ($product->exists) @method('PUT') @endif
        <x-nit::card :title="$product->exists ? $product->name : __('Add loan product')" :subtitle="$locked ? __('Loans use this product: its accounts are fixed.') : null">
            <div class="nx-form-grid">
                <x-nit::input name="code" :label="__('Code')" :value="$product->code" required maxlength="20" />
                <x-nit::input name="name" :label="__('Name')" :value="$product->name" required />
                <x-nit::select name="interest_method" :label="__('Interest / service charge')" :options="$methods" :value="$product->interest_method?->value" required />
                <x-nit::input name="interest_rate" inputmode="decimal" :label="__('Rate % a year')" :value="$product->interest_rate" :hint="__('Flat: on the full principal. Declining: on the principal still owed.')" />
                <x-nit::select name="repayment_frequency" :label="__('Repayment')" :options="$frequencies" :value="$product->repayment_frequency?->value" required />
                <x-nit::input name="processing_fee_rate" inputmode="decimal" :label="__('Processing fee %')" :value="$product->processing_fee_rate" :hint="__('Kept from the amount paid out, booked as fee income.')" />
                <x-nit::input name="min_installments" type="number" min="1" :label="__('Fewest installments')" :value="$product->min_installments" required />
                <x-nit::input name="max_installments" type="number" min="1" :label="__('Most installments')" :value="$product->max_installments" required />
                <x-nit::input name="min_amount" inputmode="decimal" :label="__('Smallest loan')" :value="$product->min_amount" required />
                <x-nit::input name="max_amount" inputmode="decimal" :label="__('Largest loan')" :value="$product->max_amount" :hint="__('Empty = no limit')" />
                <x-nit::input name="grace_days" type="number" min="0" :label="__('Grace days')" :value="$product->grace_days" :hint="__('Days after the due date before a penalty is asked for.')" />
                <x-nit::select name="penalty_type" :label="__('Late payment penalty')" :options="$penaltyTypes" :value="$product->penalty_type?->value ?? 'none'" required :hint="__('Charged once per late installment; the repayment form fills it in. Changes apply to running loans too.')" />
                <x-nit::input name="penalty_value" inputmode="decimal" :label="__('Penalty amount or %')" :value="$product->penalty_value" :hint="__('Fixed: taka per late installment. Percent: of what is still unpaid on it.')" />
                <div></div>
                <x-nit::select name="receivable_account_id" :label="__('Loan receivable account')" :options="$receivables" :value="$product->receivable_account_id" required :placeholder="__('Choose…')" />
                <x-nit::select name="interest_income_account_id" :label="__('Interest income account')" :options="$incomeAccounts" :value="$product->interest_income_account_id" required :placeholder="__('Choose…')" />
                <x-nit::select name="penalty_income_account_id" :label="__('Penalty income account')" :options="$incomeAccounts" :value="$product->penalty_income_account_id" required :placeholder="__('Choose…')" />
                <x-nit::select name="fee_income_account_id" :label="__('Fee income account')" :options="$incomeAccounts" :value="$product->fee_income_account_id" required :placeholder="__('Choose…')" />
                <div class="nx-field">
                    <input type="hidden" name="is_active" value="0">
                    <label class="nx-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ? '1' : '0') === '1')> <span>{{ __('Active') }}</span></label>
                </div>
                <x-nit::textarea name="description" :label="__('Description')" :value="$product->description" rows="2" wrapper-class="nx-full" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.loan-products.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Save') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
