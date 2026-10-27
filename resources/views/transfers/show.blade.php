@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Transfer :number', ['number' => $transfer->number]))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$transfer->number" :subtitle="$transfer->description">
        <x-slot:actions>@include('finance::partials.status', ['status' => $transfer->status])</x-slot:actions>
        <dl class="fin-facts">
            <dt>{{ __('Date') }}</dt><dd>{{ $transfer->transfer_date->format('j M Y') }}</dd>
            <dt>{{ __('Branch') }}</dt><dd>{{ $transfer->branch?->name }}</dd>
            <dt>{{ __('From') }}</dt><dd>{{ $transfer->fromAccount?->label() }}</dd>
            <dt>{{ __('To') }}</dt><dd>{{ $transfer->toAccount?->label() }}</dd>
            <dt>{{ __('Amount') }}</dt><dd><strong>@finmoney($transfer->amount)</strong></dd>
            @if ($transfer->reference)<dt>{{ __('Reference') }}</dt><dd>{{ $transfer->reference }}</dd>@endif
            <dt>{{ __('Written by') }}</dt><dd>{{ $transfer->creator?->name ?? '—' }}</dd>
            @if ($transfer->posted_at)<dt>{{ __('Posted') }}</dt><dd>{{ $transfer->poster?->name }} · {{ $transfer->posted_at->format('j M Y H:i') }}</dd>@endif
            @include('finance::partials.entry-link', ['record' => $transfer])
        </dl>
        @if ($transfer->journalEntry)<div class="mt-5">@include('finance::partials.entry-lines', ['entry' => $transfer->journalEntry])</div>@endif
        <x-slot:footer>
            <x-nit::button variant="light" :href="route('finance.transfers.index')" icon="fas fa-arrow-left">{{ __('Transfers') }}</x-nit::button>
            @can('update', $transfer)
                <form method="POST" action="{{ route('finance.transfers.cancel', $transfer) }}" class="d-inline" data-nx-confirm="{{ __('Cancel this draft?') }}" data-nx-confirm-button="{{ __('Cancel draft') }}">@csrf<button class="nx-btn nx-btn-light" type="submit">{{ __('Cancel draft') }}</button></form>
                <x-nit::button variant="light" :href="route('finance.transfers.edit', $transfer)" icon="fas fa-pen">{{ __('Edit') }}</x-nit::button>
            @endcan
            @can('post', $transfer)
                <form method="POST" action="{{ route('finance.transfers.post', $transfer) }}" class="d-inline" data-nx-confirm="{{ __('Post :number?', ['number' => $transfer->number]) }}" data-nx-confirm-button="{{ __('Post') }}">@csrf<button class="nx-btn nx-btn-primary" type="submit"><i class="fas fa-check" aria-hidden="true"></i> {{ __('Post') }}</button></form>
            @endcan
            @can('reverse', $transfer)
                @include('finance::partials.reverse', ['action' => route('finance.transfers.reverse', $transfer), 'label' => $transfer->number])
            @endcan
        </x-slot:footer>
    </x-nit::card>
</div>
@endsection
