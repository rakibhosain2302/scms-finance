@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Trial balance'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    <x-nit::card :title="__('Trial balance as of :date', ['date' => $to->format('j M Y')])" :subtitle="$branchLabel" :padding="false">
        @include('finance::partials.period-filters')
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th class="fin-num">{{ __('Debit') }}</th><th class="fin-num">{{ __('Credit') }}</th></tr></thead>
                <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr>
                            <td><a href="{{ route('finance.reports.ledger', ['account_id' => $row['account']->id, 'to' => $to->toDateString(), 'branch_id' => $branchId]) }}">{{ $row['account']->label() }}</a></td>
                            <td class="nx-muted">{{ $row['account']->type->label() }}</td>
                            <td class="fin-num">{{ $row['debit']->isZero() ? '' : $row['debit']->format() }}</td>
                            <td class="fin-num">{{ $row['credit']->isZero() ? '' : $row['credit']->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fin-total"><th colspan="2">{{ __('Total') }} @if ($report['debit']->equals($report['credit']))<x-nit::badge tone="success" icon="fas fa-check">{{ __('Balanced') }}</x-nit::badge>@else<x-nit::badge tone="danger">{{ __('Out of balance') }}</x-nit::badge>@endif</th>
                        <th class="fin-num">{{ $report['debit']->format() }}</th><th class="fin-num">{{ $report['credit']->format() }}</th></tr>
                </tfoot>
            </table>
        </div>
    </x-nit::card>
</div>
@endsection
