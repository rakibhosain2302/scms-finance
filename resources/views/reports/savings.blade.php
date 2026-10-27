@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Savings summary'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    <div class="fin-tiles mb-5">
        <div class="fin-tile"><small>{{ __('Member savings') }}</small><strong>{{ $report['total']->format() }}</strong></div>
        <div class="fin-tile"><small>{{ __('Deposited this year') }}</small><strong>{{ $period['deposits']->format() }}</strong></div>
        <div class="fin-tile"><small>{{ __('Withdrawn this year') }}</small><strong>{{ $period['withdrawals']->format() }}</strong></div>
    </div>
    <x-nit::card :title="__('Savings per scheme · :date', ['date' => $to->format('j M Y')])" :subtitle="$branchLabel" :padding="false">
        @include('finance::partials.period-filters')
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>{{ __('Scheme') }}</th><th class="fin-num">{{ __('Open accounts') }}</th><th class="fin-num">{{ __('Balance') }}</th></tr></thead>
                <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr><td>{{ $row['scheme']->name }}</td><td class="fin-num">{{ $row['accounts'] }}</td><td class="fin-num">{{ $row['balance']->format() }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fin-total"><th>{{ __('Total') }}</th><th class="fin-num">{{ array_sum(array_column($report['rows'], 'accounts')) }}</th><th class="fin-num">{{ $report['total']->format() }}</th></tr></tfoot>
            </table>
        </div>
    </x-nit::card>
</div>
@endsection
