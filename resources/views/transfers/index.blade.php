@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Transfers'))
@include('finance::partials.styles')

@can(\SCMS\Finance\Support\Abilities::TRANSFER_CREATE)
@push('buttons')
<a href="{{ route('finance.transfers.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('New transfer') }}</a>
@endpush
@endcan

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$transfers" :title="__('Transfers')" :subtitle="__('Cash to bank, bank to cash, bank to bank. Never income or expense.')"
        :columns="['number' => __('Number'), 'date' => __('Date'), 'from' => __('From'), 'to' => __('To'), 'amount' => ['label' => __('Amount'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('No transfers yet')" empty-icon="fas fa-exchange-alt">
        <x-slot:filters>
            <select name="status" class="form-select nx-filter-select" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('From') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('To') }}">
        </x-slot:filters>
        @foreach ($transfers as $t)
            <tr>
                <td><a href="{{ route('finance.transfers.show', $t) }}">{{ $t->number }}</a></td>
                <td>{{ $t->transfer_date->format('j M Y') }}</td>
                <td>{{ $t->fromAccount?->name }}</td>
                <td>{{ $t->toAccount?->name }}</td>
                <td class="fin-num">@finmoney($t->amount)</td>
                <td>@include('finance::partials.status', ['status' => $t->status])</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
