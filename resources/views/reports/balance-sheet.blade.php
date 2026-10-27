@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Balance sheet'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    <x-nit::card :title="__('Balance sheet as of :date', ['date' => $to->format('j M Y')])" :subtitle="$branchLabel" :padding="false">
        @include('finance::partials.period-filters')
        <div class="row g-0">
            <div class="col-lg-6">
                <table class="nx-tbl">
                    <thead><tr><th colspan="2">{{ __('Assets') }}</th></tr></thead>
                    <tbody>
                        @foreach ($report['asset'] as $row)<tr><td>{{ $row['account']->label() }}</td><td class="fin-num">{{ $row['amount']->format() }}</td></tr>@endforeach
                    </tbody>
                    <tfoot><tr class="fin-total"><th>{{ __('Total assets') }}</th><th class="fin-num">{{ $report['total_asset']->format() }}</th></tr></tfoot>
                </table>
            </div>
            <div class="col-lg-6">
                <table class="nx-tbl">
                    <thead><tr><th colspan="2">{{ __('Liabilities') }}</th></tr></thead>
                    <tbody>
                        @foreach ($report['liability'] as $row)<tr><td>{{ $row['account']->label() }}</td><td class="fin-num">{{ $row['amount']->format() }}</td></tr>@endforeach
                        <tr class="fin-total"><td>{{ __('Total liabilities') }}</td><td class="fin-num">{{ $report['total_liability']->format() }}</td></tr>
                        <tr><th colspan="2" class="pt-5">{{ __('Equity') }}</th></tr>
                        @foreach ($report['equity'] as $row)<tr><td>{{ $row['account']->label() }}</td><td class="fin-num">{{ $row['amount']->format() }}</td></tr>@endforeach
                        <tr><td>{{ __('Surplus not yet closed') }}</td><td @class(['fin-num', 'fin-neg' => $report['unclosed']->isNegative()])>{{ $report['unclosed']->format() }}</td></tr>
                        <tr class="fin-total"><td>{{ __('Total equity') }}</td><td class="fin-num">{{ $report['total_equity']->format() }}</td></tr>
                    </tbody>
                    <tfoot><tr class="fin-total"><th>{{ __('Liabilities and equity') }}</th><th class="fin-num">{{ $report['total_liability']->plus($report['total_equity'])->format() }}</th></tr></tfoot>
                </table>
            </div>
        </div>
        @unless ($report['total_asset']->equals($report['total_liability']->plus($report['total_equity'])))
            <div class="nx-alert m-5" role="alert"><i class="fas fa-exclamation-triangle mt-1" aria-hidden="true"></i> <span>{{ __('Assets do not equal liabilities and equity. Check the trial balance.') }}</span></div>
        @endunless
    </x-nit::card>
</div>
@endsection
