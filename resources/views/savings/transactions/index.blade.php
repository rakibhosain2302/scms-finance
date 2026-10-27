@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Deposits & withdrawals'))
@include('finance::partials.styles')

@push('buttons')
@can(\SCMS\Finance\Support\Abilities::SAVINGS_DEPOSIT)<a href="{{ route('finance.savings-transactions.create', ['type' => 'deposit']) }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('Deposit') }}</a>@endcan
@can(\SCMS\Finance\Support\Abilities::SAVINGS_WITHDRAW)<a href="{{ route('finance.savings-transactions.create', ['type' => 'withdrawal']) }}" class="btn btn-sm btn-light fw-bold"><i class="fas fa-minus" aria-hidden="true"></i> {{ __('Withdrawal') }}</a>@endcan
@endpush

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$transactions" :title="__('Deposits & withdrawals')" search
        :columns="['number' => __('Number'), 'date' => __('Date'), 'account' => __('Account'), 'type' => __('Type'), 'amount' => ['label' => __('Amount'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('No transactions yet')" empty-icon="fas fa-receipt">
        <x-slot:filters>
            <select name="type" class="form-select nx-filter-select" aria-label="{{ __('Type') }}"><option value="">{{ __('Deposits and withdrawals') }}</option>@foreach ($types as $v => $l)<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>@endforeach</select>
            <select name="status" class="form-select nx-filter-select" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('From') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('To') }}">
        </x-slot:filters>
        @foreach ($transactions as $t)
            <tr>
                <td><a href="{{ route('finance.savings-transactions.show', $t) }}">{{ $t->number }}</a></td>
                <td>{{ $t->transaction_date->format('j M Y') }}</td>
                <td>{{ $t->savingsAccount?->account_number }}<span class="nx-muted fs-7 d-block">{{ $members[$t->savingsAccount?->member_id] ?? '' }}</span></td>
                <td>{{ $t->type->label() }}</td>
                <td class="fin-num">@finmoney($t->amount)</td>
                <td>@include('finance::partials.status', ['status' => $t->status])</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
