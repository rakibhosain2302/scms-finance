@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Repayment of :number', ['number' => $loan->loan_number]))
@section('nx-inline-errors', '1')
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ route('finance.loan-repayments.store', $loan) }}" data-nx-once novalidate>
        @csrf
        <x-nit::card :title="__('Repayment of :number', ['number' => $loan->loan_number])" :subtitle="$member">
            <div class="fin-tiles mb-5">
                <div class="fin-tile"><small>{{ __('Due now') }}</small><strong @class(['fin-neg' => $dueNow->isPositive()])>{{ $dueNow->format() }}</strong></div>
                @if ($next)<div class="fin-tile"><small>{{ __('Next installment (#:n, :date)', ['n' => $next->installment_no, 'date' => $next->due_date->format('j M')]) }}</small><strong>{{ $next->remaining()->format() }}</strong></div>@endif
                <div class="fin-tile"><small>{{ __('Pays the loan off') }}</small><strong>{{ $payoff->format() }}</strong></div>
                <div class="fin-tile"><small>{{ __('Principal outstanding') }}</small><strong>{{ $outstanding->format() }}</strong></div>
                @if ($penaltyRule)<div class="fin-tile"><small>{{ __('Penalty owed (product rule)') }}</small><strong @class(['fin-neg' => $penaltyOwed->isPositive()])>{{ $penaltyOwed->format() }}</strong></div>@endif
            </div>
            <p class="nx-muted">{{ __('The payment goes to the oldest unpaid installments first, interest before principal. A penalty is taken first and booked as penalty income.') }}</p>
            <div class="nx-form-grid">
                <x-nit::input name="amount" inputmode="decimal" :label="__('Amount received')" :value="($dueNow->isPositive() ? $dueNow : ($next ? $next->remaining() : \SCMS\Finance\Support\Money::zero()))->plus($penaltyOwed)->toString()" required autofocus />
                <x-nit::input name="penalty_amount" inputmode="decimal" :label="__('Of which penalty')" :value="$penaltyOwed->toString()" :hint="$penaltyRule ? __('Part of the amount above; at most :owed under the product rule (less waives the rest for now).', ['owed' => $penaltyOwed->format()]) : __('Part of the amount above.')" />
                <x-nit::input name="repayment_date" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-nit::select name="cash_bank_account_id" :label="__('Received into')" :options="$cashAccounts" required :placeholder="__('Choose…')" />
                <x-nit::input name="reference" :label="__('Reference')" />
                <x-nit::input name="description" :label="__('Note')" maxlength="500" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.loans.show', $loan)">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Post repayment') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
