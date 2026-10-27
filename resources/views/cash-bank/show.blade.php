@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $cash->name)
@include('finance::partials.styles')

@can('update', $cash)
@push('buttons')
<a href="{{ route('finance.cash-bank.edit', $cash) }}" class="btn btn-sm btn-light fw-bold"><i class="fas fa-pen" aria-hidden="true"></i> {{ __('Edit') }}</a>
@endpush
@endcan

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$cash->label()" :subtitle="$cash->kind->label().' · '.$cash->branch?->name.' · '.$cash->account?->label()">
        <div class="fin-tiles">
            <div class="fin-tile"><small>{{ __('Balance now') }}</small><strong @class(['fin-neg' => $balance->isNegative()])>{{ $balance->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Brought forward') }}</small><strong>{{ $book['opening']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Received') }}</small><strong>{{ $book['receipts']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Paid out') }}</small><strong>{{ $book['payments']->format() }}</strong></div>
        </div>
    </x-nit::card>

    <x-nit::card :title="__('Statement')" class="mt-5" :padding="false">
        <form method="get" class="nx-filters fin-no-print">
            <label class="d-flex align-items-center gap-2">{{ __('From') }} <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></label>
            <label class="d-flex align-items-center gap-2">{{ __('To') }} <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></label>
            <button type="submit" class="nx-btn nx-btn-primary">{{ __('Show') }}</button>
        </form>
        @include('finance::reports.cashbook-table', ['book' => $book, 'showAccount' => false])
    </x-nit::card>
</div>
@endsection
