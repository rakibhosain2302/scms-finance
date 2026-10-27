@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Disburse :number', ['number' => $loan->loan_number]))
@section('nx-inline-errors', '1')
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ route('finance.loans.disburse', $loan) }}" data-nx-once novalidate>
        @csrf
        <x-nit::card :title="__('Disburse :number', ['number' => $loan->loan_number])" :subtitle="$member">
            <div class="fin-tiles mb-5">
                <div class="fin-tile"><small>{{ __('Principal') }}</small><strong>@finmoney($loan->principal)</strong></div>
                <div class="fin-tile"><small>{{ __('Processing fee kept') }}</small><strong>@finmoney($loan->processing_fee)</strong></div>
                <div class="fin-tile"><small>{{ __('Paid to the member') }}</small><strong>{{ $loan->money('principal')->minus($loan->processing_fee)->format() }}</strong></div>
            </div>
            <p class="nx-muted">{{ __('Dr Loan receivable (principal), Cr cash or bank (amount paid out), Cr fee income (fee). The schedule is written from the first due date.') }}</p>
            <div class="nx-form-grid">
                <x-nit::input name="disbursed_on" type="date" :label="__('Paid out on')" :value="today()->toDateString()" required />
                <x-nit::input name="first_due_on" type="date" :label="__('First installment due')" :value="$firstDue->toDateString()" required />
                <x-nit::select name="cash_bank_account_id" :label="__('Paid from')" :options="$cashAccounts" required :placeholder="__('Choose…')" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.loans.show', $loan)">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-money-bill-wave">{{ __('Disburse') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
