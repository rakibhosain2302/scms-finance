@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Loans'))
@include('finance::partials.styles')

@can(\SCMS\Finance\Support\Abilities::LOAN_CREATE)
@push('buttons')
<a href="{{ route('finance.loans.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('New application') }}</a>
@endpush
@endcan

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$loans" :title="__('Loans')" search
        :columns="['number' => __('Loan'), 'member' => __('Member'), 'product' => __('Product'), 'principal' => ['label' => __('Principal'), 'class' => 'fin-num'], 'outstanding' => ['label' => __('Outstanding'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('No loans yet')" empty-icon="fas fa-hand-holding-usd">
        <x-slot:filters>
            <select name="product" class="form-select nx-filter-select" aria-label="{{ __('Product') }}"><option value="">{{ __('Any product') }}</option>@foreach ($products as $v => $l)<option value="{{ $v }}" @selected((string) request('product') === (string) $v)>{{ $l }}</option>@endforeach</select>
            <select name="status" class="form-select nx-filter-select" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
        </x-slot:filters>
        @foreach ($loans as $loan)
            <tr>
                <td><a href="{{ route('finance.loans.show', $loan) }}">{{ $loan->loan_number }}</a><span class="nx-muted fs-7 d-block">{{ $loan->applied_on->format('j M Y') }} · {{ $loan->branch?->name }}</span></td>
                <td>{{ $members[$loan->member_id] ?? '#'.$loan->member_id }}</td>
                <td>{{ $loan->product?->name }}</td>
                <td class="fin-num">@finmoney($loan->principal)</td>
                <td class="fin-num" title="{{ __('Cached; the loan page shows the ledger figure.') }}">{{ $loan->status->isRunning() ? $loan->money('principal_outstanding')->format() : '—' }}</td>
                <td>@include('finance::partials.status', ['status' => $loan->status])</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
