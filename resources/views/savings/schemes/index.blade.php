@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Savings schemes'))
@include('finance::partials.styles')

@push('buttons')
<a href="{{ route('finance.savings-schemes.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('Add scheme') }}</a>
@endpush

@section('content')
<div class="nx-page">
    <x-nit::table :rows="$schemes" :title="__('Savings schemes')" :subtitle="__('Member deposits are a liability of the samiti, never income.')" row-actions
        :columns="['name' => __('Scheme'), 'frequency' => __('Frequency'), 'deposit' => ['label' => __('Deposit range'), 'class' => 'fin-num'], 'minimum' => ['label' => __('Minimum balance'), 'class' => 'fin-num'], 'withdraw' => __('Withdrawals'), 'accounts' => __('Accounts'), 'status' => __('Status')]"
        :empty="__('No schemes yet')" empty-icon="fas fa-piggy-bank">
        @foreach ($schemes as $s)
            <tr>
                <td><strong>{{ $s->name }}</strong><span class="nx-muted fs-7 d-block">{{ $s->code }} · {{ $s->liabilityAccount?->code }}</span></td>
                <td>{{ $s->frequency->label() }}</td>
                <td class="fin-num">@finmoney($s->min_deposit) – {{ $s->max_deposit !== null ? \SCMS\Finance\Support\Money::of($s->max_deposit)->format() : '∞' }}</td>
                <td class="fin-num">@finmoney($s->minimum_balance)</td>
                <td>{{ $s->allow_withdrawal ? ($s->max_withdrawals_per_month ? __(':n a month', ['n' => $s->max_withdrawals_per_month]) : __('Allowed')) : __('Not allowed') }}</td>
                <td>{{ $s->accounts_count }}</td>
                <td>@if ($s->is_active)<x-nit::badge tone="success">{{ __('Active') }}</x-nit::badge>@else<x-nit::badge>{{ __('Off') }}</x-nit::badge>@endif</td>
                <td class="text-end"><a href="{{ route('finance.savings-schemes.edit', $s) }}" class="nx-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit :name', ['name' => $s->name]) }}"><i class="fas fa-pen" aria-hidden="true"></i></a></td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
