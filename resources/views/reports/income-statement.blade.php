@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Income & expenditure'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    <x-nit::card :title="__('Income & expenditure · :from – :to', ['from' => $from->format('j M Y'), 'to' => $to->format('j M Y')])" :subtitle="$branchLabel" :padding="false">
        @include('finance::partials.period-filters')
        <div class="table-responsive">
            <table class="nx-tbl">
                <tbody>
                    <tr><th colspan="2" class="pt-5">{{ __('Income') }}</th></tr>
                    @forelse ($report['income'] as $row)
                        <tr><td class="fin-tree-1">{{ $row['account']->label() }}</td><td class="fin-num">{{ $row['amount']->format() }}</td></tr>
                    @empty
                        <tr><td class="fin-tree-1 nx-muted" colspan="2">{{ __('None') }}</td></tr>
                    @endforelse
                    <tr class="fin-total"><td>{{ __('Total income') }}</td><td class="fin-num">{{ $report['total_income']->format() }}</td></tr>
                    <tr><th colspan="2" class="pt-5">{{ __('Expenditure') }}</th></tr>
                    @forelse ($report['expense'] as $row)
                        <tr><td class="fin-tree-1">{{ $row['account']->label() }}</td><td class="fin-num">{{ $row['amount']->format() }}</td></tr>
                    @empty
                        <tr><td class="fin-tree-1 nx-muted" colspan="2">{{ __('None') }}</td></tr>
                    @endforelse
                    <tr class="fin-total"><td>{{ __('Total expenditure') }}</td><td class="fin-num">{{ $report['total_expense']->format() }}</td></tr>
                </tbody>
                <tfoot>
                    <tr class="fin-total"><th>{{ $report['surplus']->isNegative() ? __('Deficit') : __('Surplus') }}</th><th @class(['fin-num', 'fin-neg' => $report['surplus']->isNegative()])>{{ $report['surplus']->abs()->format() }}</th></tr>
                </tfoot>
            </table>
        </div>
        <p class="nx-hint px-5 pb-4">{{ __('Member deposits and loan principal are not income or expense and never appear here.') }}</p>
    </x-nit::card>
</div>
@endsection
