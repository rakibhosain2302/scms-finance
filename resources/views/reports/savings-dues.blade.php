@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Savings in arrears'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    <x-nit::card :title="__('Savings in arrears · :from – :to', ['from' => $from->format('j M Y'), 'to' => $to->format('j M Y')])" :subtitle="$branchLabel" :padding="false">
        @include('finance::partials.period-filters')
        @if ($rows === [])
            <x-nit::empty-state icon="fas fa-check-circle" :title="__('Every regular saver is up to date')" />
        @else
            <div class="table-responsive"><table class="nx-tbl">
                <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Member') }}</th><th>{{ __('Scheme') }}</th><th class="fin-num">{{ __('Periods') }}</th><th class="fin-num">{{ __('Expected') }}</th><th class="fin-num">{{ __('Deposited') }}</th><th class="fin-num">{{ __('In arrears') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td><a href="{{ route('finance.savings-accounts.show', $row['account']) }}">{{ $row['account']->account_number }}</a></td>
                            <td>{{ $members[$row['account']->member_id] ?? '—' }}</td>
                            <td>{{ $row['account']->scheme->name }} · {{ $row['account']->scheme->frequency->label() }}</td>
                            <td class="fin-num">{{ $row['periods'] }}</td>
                            <td class="fin-num">{{ $row['expected']->format() }}</td>
                            <td class="fin-num">{{ $row['deposited']->format() }}</td>
                            <td class="fin-num fin-neg">{{ $row['arrears']->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fin-total"><th colspan="6">{{ __('Total in arrears') }}</th><th class="fin-num">{{ \SCMS\Finance\Support\Money::sum(array_column($rows, 'arrears'))->format() }}</th></tr></tfoot>
            </table></div>
        @endif
    </x-nit::card>
</div>
@endsection
