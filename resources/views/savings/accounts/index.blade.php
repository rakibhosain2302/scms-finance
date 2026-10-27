@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Savings accounts'))
@include('finance::partials.styles')

@can(\SCMS\Finance\Support\Abilities::SAVINGS_MANAGE)
@push('buttons')
<a href="{{ route('finance.savings-accounts.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('Open account') }}</a>
@endpush
@endcan

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$accounts" :title="__('Savings accounts')" search
        :columns="['number' => __('Account'), 'member' => __('Member'), 'scheme' => __('Scheme'), 'opened' => __('Opened'), 'balance' => ['label' => __('Balance'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('No savings accounts yet')" empty-icon="fas fa-piggy-bank">
        <x-slot:filters>
            <select name="scheme" class="form-select nx-filter-select" aria-label="{{ __('Scheme') }}"><option value="">{{ __('Any scheme') }}</option>@foreach ($schemes as $v => $l)<option value="{{ $v }}" @selected((string) request('scheme') === (string) $v)>{{ $l }}</option>@endforeach</select>
            <select name="status" class="form-select nx-filter-select" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
        </x-slot:filters>
        @foreach ($accounts as $a)
            <tr>
                <td><a href="{{ route('finance.savings-accounts.show', $a) }}">{{ $a->account_number }}</a><span class="nx-muted fs-7 d-block">{{ $a->branch?->name }}</span></td>
                <td>{{ $members[$a->member_id] ?? '#'.$a->member_id }}</td>
                <td>{{ $a->scheme?->name }}</td>
                <td>{{ $a->opened_on->format('j M Y') }}</td>
                <td class="fin-num" title="{{ __('Cached; the account page shows the ledger balance.') }}">@finmoney($a->balance)</td>
                <td>@include('finance::partials.status', ['status' => $a->status])</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
