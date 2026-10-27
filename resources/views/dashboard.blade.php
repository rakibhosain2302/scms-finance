@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Finance'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @php($t = $summary['today'])
    <x-nit::card :title="__('Today')" :subtitle="now()->format('l, j F Y')">
        <div class="fin-tiles">
            @foreach ([
                'deposits' => __('Savings collected'), 'withdrawals' => __('Savings withdrawn'), 'principal_repaid' => __('Loan principal repaid'),
                'interest' => __('Loan interest'), 'disbursed' => __('Loans disbursed'), 'income' => __('Income'), 'expense' => __('Expense'),
            ] as $key => $label)
                <div class="fin-tile"><small>{{ $label }}</small><strong>{{ $t[$key]->format() }}</strong></div>
            @endforeach
        </div>
    </x-nit::card>

    <x-nit::card :title="__('Balances')" :subtitle="__('From posted entries')" class="mt-5">
        <div class="fin-tiles">
            <div class="fin-tile"><small>{{ __('Cash in hand') }}</small><strong>{{ $summary['cash']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Bank') }}</small><strong>{{ $summary['bank']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Member savings') }}</small><strong>{{ $summary['savings']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Loans outstanding') }}</small><strong>{{ $summary['loans']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Overdue') }}</small><strong @class(['fin-neg' => $summary['overdue']->isPositive()])>{{ $summary['overdue']->format() }}</strong>
                <span class="nx-muted fs-7">{{ trans_choice(':count loan|:count loans', $summary['overdue_loans'], ['count' => $summary['overdue_loans']]) }}</span></div>
        </div>
    </x-nit::card>

    <div class="d-flex flex-wrap gap-2 mt-5 fin-no-print">
        @can(\SCMS\Finance\Support\Abilities::SAVINGS_DEPOSIT)<x-nit::button :href="route('finance.savings-transactions.create', ['type' => 'deposit'])" icon="fas fa-plus">{{ __('Deposit') }}</x-nit::button>@endcan
        @can(\SCMS\Finance\Support\Abilities::SAVINGS_WITHDRAW)<x-nit::button variant="light" :href="route('finance.savings-transactions.create', ['type' => 'withdrawal'])" icon="fas fa-minus">{{ __('Withdrawal') }}</x-nit::button>@endcan
        @can(\SCMS\Finance\Support\Abilities::LOAN_VIEW)<x-nit::button variant="light" :href="route('finance.loans.index', ['status' => 'active'])" icon="fas fa-hand-holding-usd">{{ __('Running loans') }}</x-nit::button>@endcan
        @can(\SCMS\Finance\Support\Abilities::INCOME_CREATE)<x-nit::button variant="light" :href="route('finance.incomes.create')" icon="fas fa-arrow-down">{{ __('Income') }}</x-nit::button>@endcan
        @can(\SCMS\Finance\Support\Abilities::EXPENSE_CREATE)<x-nit::button variant="light" :href="route('finance.expenses.create')" icon="fas fa-arrow-up">{{ __('Expense') }}</x-nit::button>@endcan
        @can(\SCMS\Finance\Support\Abilities::REPORT_VIEW)<x-nit::button variant="light" :href="route('finance.reports.cashbook')" icon="fas fa-book-open">{{ __('Cash book') }}</x-nit::button>@endcan
    </div>
</div>
@endsection
