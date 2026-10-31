@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Finance reports'))

@section('content')
<div class="nx-page">
    @php($reports = [
        ['finance.reports.activity', ['group' => 'day'], 'fas fa-calendar-day', __('Daily collection & payments'), __('Deposits, withdrawals, loans, income and expense per day.')],
        ['finance.reports.activity', ['group' => 'month', 'from' => today()->startOfYear()->toDateString()], 'fas fa-calendar-alt', __('Monthly summary'), __('The same figures per month: collection, withdrawal, loans, cash flow.')],
        ['finance.reports.cashbook', [], 'fas fa-book-open', __('Cash book'), __('Receipts and payments of cash and bank with running balance.')],
        ['finance.reports.receipts-payments', [], 'fas fa-exchange-alt', __('Receipts & payments'), __('Cash and bank received and paid by account, opening to closing.')],
        ['finance.reports.ledger', [], 'fas fa-list', __('Ledger'), __('Every posting of an account with running balance.')],
        ['finance.reports.trial-balance', [], 'fas fa-balance-scale', __('Trial balance'), __('Debit and credit balance of every account.')],
        ['finance.reports.income-statement', [], 'fas fa-chart-bar', __('Income & expenditure'), __('Profit and loss of a period.')],
        ['finance.reports.balance-sheet', [], 'fas fa-landmark', __('Balance sheet'), __('Assets, liabilities and equity on a date.')],
        ['finance.reports.loans', [], 'fas fa-hand-holding-usd', __('Loan portfolio'), __('Outstanding principal and arrears per loan.')],
        ['finance.reports.loans', ['overdue' => 1], 'fas fa-exclamation-triangle', __('Overdue loans'), __('Loans with unpaid installments past due.')],
        ['finance.reports.savings', [], 'fas fa-piggy-bank', __('Savings summary'), __('Member savings per scheme.')],
        ['finance.reports.savings-dues', [], 'fas fa-user-clock', __('Savings in arrears'), __('Daily, weekly and monthly savers behind on their regular deposit.')],
        ['finance.reports.branches', [], 'fas fa-code-branch', __('Branch summary'), __('Balances and activity of each branch.')],
    ])
    <div class="row g-5">
        @foreach ($reports as [$route, $params, $icon, $title, $text])
            <div class="col-md-6 col-xl-4">
                <a href="{{ route($route, $params) }}" class="text-reset text-decoration-none">
                    <x-nit::card class="h-100">
                        <div class="d-flex gap-4 align-items-start">
                            <i class="{{ $icon }} fs-2 text-primary mt-1" aria-hidden="true"></i>
                            <div><strong class="d-block mb-1">{{ $title }}</strong><span class="nx-muted">{{ $text }}</span></div>
                        </div>
                    </x-nit::card>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection
