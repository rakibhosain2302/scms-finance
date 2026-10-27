@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Cash book'))
@include('finance::partials.styles')

@section('report-filters')
    <select name="kind" class="form-select nx-filter-select" aria-label="{{ __('Kind') }}">
        <option value="">{{ __('Cash and bank') }}</option>
        @foreach (\SCMS\Finance\Enums\CashBankKind::options() as $v => $l)<option value="{{ $v }}" @selected($kind?->value === $v)>{{ $l }}</option>@endforeach
    </select>
    <select name="cash_bank_account_id" class="form-select nx-filter-select" aria-label="{{ __('Account') }}">
        <option value="">{{ __('Every account') }}</option>
        @foreach ($cashAccounts as $v => $l)<option value="{{ $v }}" @selected((int) $cashId === (int) $v)>{{ $l }}</option>@endforeach
    </select>
@endsection

@section('content')
<div class="nx-page">
    <x-nit::card :title="__('Cash book · :from – :to', ['from' => $from->format('j M Y'), 'to' => $to->format('j M Y')])" :subtitle="$branchLabel" :padding="false">
        @include('finance::partials.period-filters')
        @include('finance::reports.cashbook-table', ['book' => $book, 'showAccount' => true])
    </x-nit::card>
</div>
@endsection
