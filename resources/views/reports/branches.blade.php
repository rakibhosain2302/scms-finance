@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Branch summary'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    <x-nit::card :title="__('Branch summary · :from – :to', ['from' => $from->format('j M Y'), 'to' => $to->format('j M Y')])" :subtitle="__('Balances on :date; activity in the period.', ['date' => $to->format('j M Y')])" :padding="false">
        @include('finance::partials.period-filters')
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>{{ __('Branch') }}</th><th class="fin-num">{{ __('Cash') }}</th><th class="fin-num">{{ __('Bank') }}</th><th class="fin-num">{{ __('Savings') }}</th><th class="fin-num">{{ __('Loans out') }}</th><th class="fin-num">{{ __('Deposits') }}</th><th class="fin-num">{{ __('Withdrawals') }}</th><th class="fin-num">{{ __('Income') }}</th><th class="fin-num">{{ __('Expense') }}</th><th class="fin-num">{{ __('Surplus') }}</th></tr></thead>
                <tbody>
                    @php($zero = \SCMS\Finance\Support\Money::zero())
                    @foreach (collect(array_keys($balances))->merge(array_keys($activity))->unique()->sortBy(fn ($id) => $branchNames[$id] ?? '') as $id)
                        @php($b = $balances[$id] ?? ['cash' => $zero, 'bank' => $zero, 'savings' => $zero, 'loans' => $zero])
                        @php($a = $activity[$id] ?? null)
                        <tr>
                            <td>{{ $branchNames[$id] ?? '#'.$id }}</td>
                            <td class="fin-num">{{ $b['cash']->format() }}</td>
                            <td class="fin-num">{{ $b['bank']->format() }}</td>
                            <td class="fin-num">{{ $b['savings']->format() }}</td>
                            <td class="fin-num">{{ $b['loans']->format() }}</td>
                            <td class="fin-num">{{ ($a['deposits'] ?? $zero)->format() }}</td>
                            <td class="fin-num">{{ ($a['withdrawals'] ?? $zero)->format() }}</td>
                            <td class="fin-num">{{ ($a['income'] ?? $zero)->format() }}</td>
                            <td class="fin-num">{{ ($a['expense'] ?? $zero)->format() }}</td>
                            @php($s = ($a['income'] ?? $zero)->minus($a['expense'] ?? $zero))
                            <td @class(['fin-num', 'fin-neg' => $s->isNegative()])>{{ $s->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-nit::card>
</div>
@endsection
