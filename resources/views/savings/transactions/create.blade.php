@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $type->label())
@section('nx-inline-errors', '1')
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ route('finance.savings-transactions.store') }}" data-nx-once novalidate>
        @csrf
        <input type="hidden" name="type" value="{{ $type->value }}">
        <x-nit::card :title="$type->label()" :subtitle="$type === \SCMS\Finance\Enums\SavingsTransactionType::Deposit ? __('Dr cash or bank, Cr member savings. Posted at once.') : __('Dr member savings, Cr cash or bank. Posted at once.')">
            @if ($account)
                <div class="fin-tiles mb-5">
                    <div class="fin-tile"><small>{{ __('Account') }}</small><strong>{{ $account->account_number }}</strong><span class="d-block nx-muted fs-7">{{ $account->memberLabel() }} · {{ $account->scheme->name }}</span></div>
                    <div class="fin-tile"><small>{{ __('Balance') }}</small><strong>{{ $balance->format() }}</strong></div>
                    @if ($type === \SCMS\Finance\Enums\SavingsTransactionType::Withdrawal)
                        <div class="fin-tile"><small>{{ __('Can be withdrawn') }}</small><strong>{{ \SCMS\Finance\Support\Money::max($balance->minus($account->scheme->minimum_balance), 0)->format() }}</strong></div>
                    @endif
                </div>
            @endif
            <div class="nx-form-grid">
                @if ($account)
                    <input type="hidden" name="savings_account_id" value="{{ $account->id }}">
                @else
                    <x-nit::select name="savings_account_id" :label="__('Savings account')" :options="$accounts" required :placeholder="__('Choose…')" :hint="__('Or open the account page and use its buttons.')" />
                @endif
                <x-nit::input name="amount" inputmode="decimal" :label="__('Amount')" required autofocus />
                <x-nit::input name="transaction_date" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-nit::select name="cash_bank_account_id" :label="$type === \SCMS\Finance\Enums\SavingsTransactionType::Deposit ? __('Received into') : __('Paid from')" :options="$cashAccounts" required :placeholder="__('Choose…')" />
                <x-nit::input name="reference" :label="__('Reference')" :hint="__('Passbook or slip number')" />
                <x-nit::input name="description" :label="__('Note')" wrapper-class="nx-full" maxlength="500" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="$account ? route('finance.savings-accounts.show', $account) : route('finance.savings-transactions.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Post :type', ['type' => strtolower($type->label())]) }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
