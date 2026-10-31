@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Receipts & payments'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    <x-nit::card :title="__('Receipts & payments · :from – :to', ['from' => $from->format('j M Y'), 'to' => $to->format('j M Y')])" :subtitle="$branchLabel" :padding="false">
        @include('finance::partials.period-filters')
        <div class="row g-0">
            <div class="col-lg-6 border-end">
                <table class="nx-tbl">
                    <thead><tr><th>{{ __('Receipts') }}</th><th class="fin-num">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        <tr><td><strong>{{ __('Opening cash and bank') }}</strong></td><td class="fin-num"><strong>{{ $report['opening']->format() }}</strong></td></tr>
                        @foreach ($report['receipts'] as $row)
                            <tr><td>{{ $row['account']->code }} · {{ $row['account']->name }}</td><td class="fin-num">{{ $row['amount']->format() }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="fin-total"><th>{{ __('Total') }}</th><th class="fin-num">{{ $report['opening']->plus($report['total_receipts'])->format() }}</th></tr></tfoot>
                </table>
            </div>
            <div class="col-lg-6">
                <table class="nx-tbl">
                    <thead><tr><th>{{ __('Payments') }}</th><th class="fin-num">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @foreach ($report['payments'] as $row)
                            <tr><td>{{ $row['account']->code }} · {{ $row['account']->name }}</td><td class="fin-num">{{ $row['amount']->format() }}</td></tr>
                        @endforeach
                        <tr><td><strong>{{ __('Closing cash and bank') }}</strong></td><td class="fin-num"><strong>{{ $report['closing']->format() }}</strong></td></tr>
                    </tbody>
                    <tfoot><tr class="fin-total"><th>{{ __('Total') }}</th><th class="fin-num">{{ $report['total_payments']->plus($report['closing'])->format() }}</th></tr></tfoot>
                </table>
            </div>
        </div>
    </x-nit::card>
</div>
@endsection
