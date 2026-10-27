@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Cash & bank'))
@include('finance::partials.styles')

@can(\SCMS\Finance\Support\Abilities::CASHBANK_MANAGE)
@push('buttons')
<a href="{{ route('finance.cash-bank.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('Add account') }}</a>
@endpush
@endcan

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <div class="fin-tiles mb-5">
        <div class="fin-tile"><small>{{ __('Cash in hand') }}</small><strong>{{ $totals['cash']->format() }}</strong></div>
        <div class="fin-tile"><small>{{ __('In the bank') }}</small><strong>{{ $totals['bank']->format() }}</strong></div>
    </div>
    <x-nit::table :rows="$accounts" :title="__('Cash drawers and bank accounts')" :subtitle="__('Balances from posted entries.')"
        :columns="['name' => __('Account'), 'kind' => __('Kind'), 'branch' => __('Branch'), 'gl' => __('Ledger account'), 'balance' => ['label' => __('Balance'), 'class' => 'fin-num']]"
        :empty="__('No cash or bank account yet')" empty-icon="fas fa-university">
        <x-slot:filters>
            <select name="kind" class="form-select nx-filter-select" aria-label="{{ __('Kind') }}"><option value="">{{ __('Cash and bank') }}</option>@foreach (\SCMS\Finance\Enums\CashBankKind::options() as $v => $l)<option value="{{ $v }}" @selected(request('kind') === $v)>{{ $l }}</option>@endforeach</select>
        </x-slot:filters>
        @foreach ($accounts as $cash)
            @php($balance = $balances[$cash->id] ?? \SCMS\Finance\Support\Money::zero())
            <tr>
                <td><a href="{{ route('finance.cash-bank.show', $cash) }}">{{ $cash->label() }}</a>@if ($cash->bank_name)<span class="nx-muted fs-7 d-block">{{ $cash->bank_name }} {{ $cash->bank_branch }}</span>@endif @unless ($cash->is_active)<x-nit::badge>{{ __('Off') }}</x-nit::badge>@endunless</td>
                <td>{{ $cash->kind->label() }}</td>
                <td>{{ $cash->branch?->name }}</td>
                <td class="nx-muted">{{ $cash->account?->code }} · {{ $cash->account?->name }}</td>
                <td @class(['fin-num', 'fin-neg' => $balance->isNegative()])>{{ $balance->format() }}</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
