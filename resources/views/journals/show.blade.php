@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Journal entry :number', ['number' => $entry->number]))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$entry->number" :subtitle="$entry->description">
        <x-slot:actions>
            @include('finance::partials.status', ['status' => $entry->status])
        </x-slot:actions>
        <dl class="fin-facts mb-5">
            <dt>{{ __('Date') }}</dt><dd>{{ $entry->entry_date->format('j M Y') }}</dd>
            <dt>{{ __('Branch') }}</dt><dd>{{ $entry->branch?->name }}</dd>
            <dt>{{ __('Financial year') }}</dt><dd>{{ $entry->financialYear?->name }}</dd>
            <dt>{{ __('Source') }}</dt><dd>{{ __(ucfirst(str_replace('_', ' ', $entry->source_type))) }}@if ($entry->source_id) #{{ $entry->source_id }}@endif</dd>
            @if ($entry->reference)<dt>{{ __('Reference') }}</dt><dd>{{ $entry->reference }}</dd>@endif
            <dt>{{ __('Written by') }}</dt><dd>{{ $entry->creator?->name ?? '—' }} · {{ $entry->created_at?->format('j M Y H:i') }}</dd>
            @if ($entry->posted_at)<dt>{{ __('Posted by') }}</dt><dd>{{ $entry->poster?->name ?? __('System') }} · {{ $entry->posted_at->format('j M Y H:i') }}</dd>@endif
            @if ($entry->reversalOf)<dt>{{ __('Reverses') }}</dt><dd><a href="{{ route('finance.journals.show', $entry->reversalOf) }}">{{ $entry->reversalOf->number }}</a> — {{ $entry->reversal_reason }}</dd>@endif
            @if ($entry->reversedBy)<dt>{{ __('Reversed by') }}</dt><dd><a href="{{ route('finance.journals.show', $entry->reversedBy) }}">{{ $entry->reversedBy->number }}</a> — {{ $entry->reversedBy->reversal_reason }}</dd>@endif
        </dl>
        @include('finance::partials.entry-lines', ['entry' => $entry])
        <x-slot:footer>
            <x-nit::button variant="light" :href="route('finance.journals.index')" icon="fas fa-arrow-left">{{ __('Journal') }}</x-nit::button>
            @can('update', $entry)
                <form method="POST" action="{{ route('finance.journals.cancel', $entry) }}" class="d-inline" data-nx-confirm="{{ __('Cancel this draft?') }}" data-nx-confirm-button="{{ __('Cancel draft') }}">@csrf<button class="nx-btn nx-btn-light" type="submit">{{ __('Cancel draft') }}</button></form>
                <x-nit::button variant="light" :href="route('finance.journals.edit', $entry)" icon="fas fa-pen">{{ __('Edit') }}</x-nit::button>
            @endcan
            @can('post', $entry)
                <form method="POST" action="{{ route('finance.journals.post', $entry) }}" class="d-inline" data-nx-confirm="{{ __('Post :number?', ['number' => $entry->number]) }}" data-nx-confirm-text="{{ __('A posted entry cannot change; it can only be reversed.') }}" data-nx-confirm-button="{{ __('Post') }}">@csrf<button class="nx-btn nx-btn-primary" type="submit"><i class="fas fa-check" aria-hidden="true"></i> {{ __('Post') }}</button></form>
            @endcan
            @can('reverse', $entry)
                @include('finance::partials.reverse', ['action' => route('finance.journals.reverse', $entry), 'label' => $entry->number])
            @endcan
        </x-slot:footer>
    </x-nit::card>
</div>
@endsection
