@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $title)
@include('finance::partials.styles')

@can('finance_'.$kind.'_create')
@push('buttons')
<a href="{{ route("finance.{$route}.create") }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('New :thing', ['thing' => $singular]) }}</a>
@endpush
@endcan

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$vouchers" :title="$title" search
        :columns="['number' => __('Number'), 'date' => __('Date'), 'account' => __('Account'), 'party' => $partyLabel, 'paid' => __('Cash / bank'), 'amount' => ['label' => __('Amount'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('Nothing yet')" :empty-icon="$icon">
        <x-slot:filters>
            <select name="account" class="form-select nx-filter-select" aria-label="{{ __('Account') }}"><option value="">{{ __('Any account') }}</option>@foreach ($accounts as $v => $l)<option value="{{ $v }}" @selected((string) request('account') === (string) $v)>{{ $l }}</option>@endforeach</select>
            <select name="status" class="form-select nx-filter-select" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('From') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('To') }}">
        </x-slot:filters>
        @foreach ($vouchers as $v)
            <tr>
                <td><a href="{{ route("finance.{$route}.show", $v->id) }}">{{ $v->number }}</a></td>
                <td>{{ $v->voucher_date->format('j M Y') }}</td>
                <td>{{ $v->account?->name }}<span class="nx-muted fs-7 d-block">{{ \Illuminate\Support\Str::limit($v->description, 50) }}</span></td>
                <td>{{ $v->{$party} }}</td>
                <td class="nx-muted">{{ $v->cashBankAccount?->name }}</td>
                <td class="fin-num">@finmoney($v->amount)</td>
                <td>@include('finance::partials.status', ['status' => $v->status])</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
