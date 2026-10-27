@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('New reconciliation'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ route('finance.reconciliations.store') }}" data-nx-once novalidate>
        @csrf
        <x-nit::card :title="__('New reconciliation')" :subtitle="__('From the bank statement: its last date and the closing balance it shows.')">
            <div class="nx-form-grid">
                <x-nit::select name="cash_bank_account_id" :label="__('Bank account')" :options="$accounts" :value="$selected" required :placeholder="__('Choose…')" />
                <x-nit::input name="statement_date" type="date" :label="__('Statement date')" :value="today()->toDateString()" required />
                <x-nit::input name="statement_balance" inputmode="decimal" :label="__('Closing balance on the statement')" required />
                <x-nit::input name="note" :label="__('Note')" maxlength="500" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.reconciliations.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-arrow-right">{{ __('Continue') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
