@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Loan repayments'))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$repayments" :title="__('Loan repayments')" :subtitle="__('Take a repayment from the loan page.')" search
        :columns="['number' => __('Number'), 'date' => __('Date'), 'loan' => __('Loan'), 'amount' => ['label' => __('Amount'), 'class' => 'fin-num'], 'principal' => ['label' => __('Principal'), 'class' => 'fin-num'], 'interest' => ['label' => __('Interest'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('No repayments yet')" empty-icon="fas fa-hand-holding-usd">
        <x-slot:filters>
            <select name="status" class="form-select nx-filter-select" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('From') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('To') }}">
        </x-slot:filters>
        @foreach ($repayments as $r)
            <tr>
                <td><a href="{{ route('finance.loan-repayments.show', $r) }}">{{ $r->number }}</a></td>
                <td>{{ $r->repayment_date->format('j M Y') }}</td>
                <td>@if ($r->loan)<a href="{{ route('finance.loans.show', $r->loan) }}">{{ $r->loan->loan_number }}</a><span class="nx-muted fs-7 d-block">{{ $members[$r->loan->member_id] ?? '' }}</span>@endif</td>
                <td class="fin-num">@finmoney($r->amount)</td>
                <td class="fin-num">@finmoney($r->principal_amount)</td>
                <td class="fin-num">{{ $r->money('interest_amount')->plus($r->penalty_amount)->format() }}</td>
                <td>@include('finance::partials.status', ['status' => $r->status])</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
