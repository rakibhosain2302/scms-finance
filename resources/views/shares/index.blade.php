@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Share capital'))
@include('finance::partials.styles')

@push('buttons')
@can(\SCMS\Finance\Support\Abilities::SHARE_MANAGE)
<a href="{{ route('finance.shares.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('Sell shares') }}</a>
<a href="{{ route('finance.shares.create', ['type' => 'refund']) }}" class="btn btn-sm btn-light fw-bold"><i class="fas fa-undo" aria-hidden="true"></i> {{ __('Refund') }}</a>
@endcan
@endpush

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$transactions" :title="__('Share capital')" :subtitle="__('Equity of the samiti owned by its members, never income.')" row-actions
        :columns="['number' => __('Number'), 'date' => __('Date'), 'member' => __('Member'), 'type' => __('Type'), 'shares' => ['label' => __('Shares'), 'class' => 'fin-num'], 'amount' => ['label' => __('Amount'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('No share transactions yet')" empty-icon="fas fa-certificate">
        <x-slot:filters>
            <select name="type" class="form-select nx-filter-select" aria-label="{{ __('Type') }}">
                <option value="">{{ __('All types') }}</option>
                <option value="purchase" @selected(request('type') === 'purchase')>{{ __('Share purchase') }}</option>
                <option value="refund" @selected(request('type') === 'refund')>{{ __('Share refund') }}</option>
            </select>
        </x-slot:filters>
        @foreach ($transactions as $t)
            <tr>
                <td><a href="{{ route('finance.shares.show', $t) }}">{{ $t->number }}</a></td>
                <td>{{ $t->transaction_date->format('j M Y') }}</td>
                <td>{{ $members[$t->member_id] ?? '#'.$t->member_id }}</td>
                <td>{{ $t->typeLabel() }}</td>
                <td class="fin-num">{{ $t->shares ?? '—' }}</td>
                <td class="fin-num">@finmoney($t->amount)</td>
                <td>@include('finance::partials.status', ['status' => $t->status])</td>
                <td class="text-end"><a href="{{ route('finance.shares.show', $t) }}" class="nx-icon-btn" title="{{ __('Open') }}" aria-label="{{ __('Open') }}"><i class="fas fa-eye" aria-hidden="true"></i></a></td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
