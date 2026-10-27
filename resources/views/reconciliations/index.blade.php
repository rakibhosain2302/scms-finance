@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Bank reconciliation'))
@include('finance::partials.styles')

@push('buttons')
<a href="{{ route('finance.reconciliations.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('New reconciliation') }}</a>
@endpush

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$reconciliations" :title="__('Bank reconciliation')" :subtitle="__('Match each bank statement with the ledger: tick the lines the bank shows until the balances agree.')" row-actions
        :columns="['account' => __('Account'), 'branch' => __('Branch'), 'date' => __('Statement date'), 'balance' => ['label' => __('Statement balance'), 'class' => 'fin-num'], 'status' => __('Status'), 'by' => __('By')]"
        :empty="__('No reconciliations yet')" empty-icon="fas fa-balance-scale">
        @foreach ($reconciliations as $r)
            <tr>
                <td><a href="{{ route('finance.reconciliations.show', $r) }}">{{ $r->cashBankAccount?->label() }}</a></td>
                <td>{{ $r->branch?->name }}</td>
                <td>{{ $r->statement_date->format('j M Y') }}</td>
                <td class="fin-num">@finmoney($r->statement_balance)</td>
                <td>@if ($r->isDraft())<x-nit::badge tone="warning">{{ __('Draft') }}</x-nit::badge>@else<x-nit::badge tone="success">{{ __('Completed') }}</x-nit::badge>@endif</td>
                <td>{{ $r->creator?->name ?? '—' }}</td>
                <td class="text-end"><a href="{{ route('finance.reconciliations.show', $r) }}" class="nx-icon-btn" title="{{ __('Open') }}" aria-label="{{ __('Open') }}"><i class="fas fa-eye" aria-hidden="true"></i></a></td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
