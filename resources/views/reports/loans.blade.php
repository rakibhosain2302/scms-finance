@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $overdueOnly ? __('Overdue loans') : __('Loan portfolio'))
@include('finance::partials.styles')

@section('report-filters')
    <label class="nx-check"><input type="checkbox" name="overdue" value="1" @checked($overdueOnly)> <span>{{ __('Overdue only') }}</span></label>
@endsection

@section('content')
<div class="nx-page">
    <div class="fin-tiles mb-5">
        <div class="fin-tile"><small>{{ __('Principal outstanding') }}</small><strong>{{ $report['outstanding']->format() }}</strong></div>
        <div class="fin-tile"><small>{{ __('Overdue') }}</small><strong @class(['fin-neg' => $report['overdue']->isPositive()])>{{ $report['overdue']->format() }}</strong><span class="nx-muted fs-7">{{ trans_choice(':count loan|:count loans', $report['overdue_loans'], ['count' => $report['overdue_loans']]) }}</span></div>
        <div class="fin-tile"><small>{{ __('Disbursed this year') }}</small><strong>{{ $period['disbursed']->format() }}</strong></div>
        <div class="fin-tile"><small>{{ __('Repaid this year (principal + interest)') }}</small><strong>{{ $period['principal_repaid']->plus($period['interest'])->format() }}</strong></div>
    </div>
    <x-nit::card :title="($overdueOnly ? __('Overdue loans') : __('Running loans')).' · '.$to->format('j M Y')" :subtitle="$branchLabel" :padding="false">
        @include('finance::partials.period-filters')
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>{{ __('Loan') }}</th><th>{{ __('Member') }}</th><th>{{ __('Product') }}</th><th>{{ __('Disbursed') }}</th><th class="fin-num">{{ __('Principal') }}</th><th class="fin-num">{{ __('Outstanding') }}</th><th class="fin-num">{{ __('Overdue') }}</th><th>{{ __('Late since') }}</th></tr></thead>
                <tbody>
                    @forelse ($report['loans'] as $loan)
                        <tr>
                            <td><a href="{{ route('finance.loans.show', $loan) }}">{{ $loan->loan_number }}</a></td>
                            <td>{{ $members[$loan->member_id] ?? '#'.$loan->member_id }}</td>
                            <td>{{ $loan->product?->name }}</td>
                            <td>{{ $loan->disbursed_on?->format('j M Y') }}</td>
                            <td class="fin-num">@finmoney($loan->principal)</td>
                            <td class="fin-num">{{ $loan->ledger_outstanding->format() }}</td>
                            <td @class(['fin-num', 'fin-neg' => $loan->overdue_amount->isPositive()])>{{ $loan->overdue_installments ? $loan->overdue_amount->format().' ('.$loan->overdue_installments.')' : '' }}</td>
                            <td>{{ $loan->overdue_since ? \Illuminate\Support\Carbon::parse($loan->overdue_since)->format('j M Y') : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="nx-muted text-center py-5">{{ __('No loans to show.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr class="fin-total"><th colspan="5">{{ __('Total') }}</th><th class="fin-num">{{ $report['outstanding']->format() }}</th><th class="fin-num">{{ $report['overdue']->format() }}</th><th></th></tr></tfoot>
            </table>
        </div>
    </x-nit::card>
</div>
@endsection
