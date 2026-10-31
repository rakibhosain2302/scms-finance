@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $transaction->typeLabel().' '.$transaction->number)
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$transaction->typeLabel().' · '.$transaction->number" :subtitle="$member">
        <x-slot:actions>@include('finance::partials.status', ['status' => $transaction->status])</x-slot:actions>
        <div class="fin-tiles mb-5">
            <div class="fin-tile"><small>{{ __('Amount') }}</small><strong>@finmoney($transaction->amount)</strong></div>
            <div class="fin-tile"><small>{{ __('Shares held now') }}</small><strong>{{ $balance->format() }}</strong></div>
        </div>
        <dl class="fin-facts">
            <dt>{{ __('Date') }}</dt><dd>{{ $transaction->transaction_date->format('j M Y') }}</dd>
            <dt>{{ __('Branch') }}</dt><dd>{{ $transaction->branch?->name }}</dd>
            <dt>{{ __('Shares') }}</dt><dd>{{ $transaction->shares ?? '—' }}</dd>
            <dt>{{ __('Cash / bank') }}</dt><dd>{{ $transaction->cashBankAccount?->label() }}</dd>
            <dt>{{ __('Entered by') }}</dt><dd>{{ $transaction->creator?->name ?? '—' }}</dd>
            @if ($transaction->reference)<dt>{{ __('Reference') }}</dt><dd>{{ $transaction->reference }}</dd>@endif
            @if ($transaction->description)<dt>{{ __('Note') }}</dt><dd>{{ $transaction->description }}</dd>@endif
        </dl>
        @include('finance::partials.entry-lines', ['entry' => $transaction->journalEntry])
        <x-slot:footer>
            <x-nit::button variant="light" :href="route('finance.shares.index')" icon="fas fa-arrow-left">{{ __('Share capital') }}</x-nit::button>
            <button type="button" class="nx-btn nx-btn-light" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> {{ __('Print receipt') }}</button>
            @if ($transaction->isPosted())
                @can(\SCMS\Finance\Support\Abilities::REVERSE)
                    @include('finance::partials.reverse', ['action' => route('finance.shares.reverse', $transaction), 'label' => $transaction->number])
                @endcan
            @endif
        </x-slot:footer>
    </x-nit::card>
</div>
@endsection
