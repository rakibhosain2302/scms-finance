@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $group === 'month' ? __('Monthly summary') : ($group === 'branch' ? __('Activity by branch') : __('Daily collection & payments')))
@include('finance::partials.styles')

@section('report-filters')
    <select name="group" class="form-select nx-filter-select" aria-label="{{ __('Per') }}">
        @foreach (['day' => __('Per day'), 'month' => __('Per month'), 'branch' => __('Per branch')] as $v => $l)<option value="{{ $v }}" @selected($group === $v)>{{ $l }}</option>@endforeach
    </select>
@endsection

@php($cols = ['deposits' => __('Deposits'), 'withdrawals' => __('Withdrawals'), 'disbursed' => __('Loans out'), 'principal_repaid' => __('Principal in'), 'interest' => __('Interest'), 'penalty' => __('Penalty'), 'fees' => __('Fees'), 'income' => __('Income'), 'expense' => __('Expense')])

@section('content')
<div class="nx-page">
    <x-nit::card :title="__(':from – :to', ['from' => $from->format('j M Y'), 'to' => $to->format('j M Y')])" :subtitle="$branchLabel.' · '.__('posted entries; reversals subtracted')" :padding="false">
        @include('finance::partials.period-filters')
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>{{ $group === 'month' ? __('Month') : ($group === 'branch' ? __('Branch') : __('Date')) }}</th>@foreach ($cols as $l)<th class="fin-num">{{ $l }}</th>@endforeach<th class="fin-num">{{ __('Cash & bank change') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $key => $m)
                        <tr>
                            <td>{{ $group === 'month' ? \Illuminate\Support\Carbon::parse($key.'-01')->format('M Y') : ($group === 'branch' ? ($branchNames[$key] ?? '#'.$key) : \Illuminate\Support\Carbon::parse($key)->format('j M Y')) }}</td>
                            @foreach ($cols as $k => $l)<td class="fin-num">{{ $m[$k]->isZero() ? '' : $m[$k]->format() }}</td>@endforeach
                            <td @class(['fin-num', 'fin-neg' => $m['cash_net']->isNegative()])>{{ $m['cash_net']->format() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($cols) + 2 }}" class="nx-muted text-center py-5">{{ __('Nothing was posted in this period.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fin-total"><th>{{ __('Total') }}</th>@foreach ($cols as $k => $l)<th class="fin-num">{{ $totals[$k]->format() }}</th>@endforeach
                        <th class="fin-num">{{ $totals['cash_net']->format() }}</th></tr>
                </tfoot>
            </table>
        </div>
        <p class="nx-hint px-5 pb-4">{{ __('Income includes loan interest, penalties and processing fees (fees are kept from the amount paid out). The last column is the net change of all cash and bank accounts; transfers between them cancel out.') }}</p>
    </x-nit::card>
</div>
@endsection
