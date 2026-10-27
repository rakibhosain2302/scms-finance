@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $voucher->number)
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$voucher->number" :subtitle="$voucher->description">
        <x-slot:actions>@include('finance::partials.status', ['status' => $voucher->status])</x-slot:actions>
        <dl class="fin-facts">
            <dt>{{ __('Date') }}</dt><dd>{{ $voucher->voucher_date->format('j M Y') }}</dd>
            <dt>{{ __('Branch') }}</dt><dd>{{ $voucher->branch?->name }}</dd>
            <dt>{{ __('Account') }}</dt><dd>{{ $voucher->account?->label() }}</dd>
            <dt>{{ $kind === 'income' ? __('Received into') : __('Paid from') }}</dt><dd>{{ $voucher->cashBankAccount?->label() }}</dd>
            <dt>{{ __('Amount') }}</dt><dd><strong>@finmoney($voucher->amount)</strong></dd>
            @if ($voucher->{$party})<dt>{{ $partyLabel }}</dt><dd>{{ $voucher->{$party} }}</dd>@endif
            @if ($voucher->reference)<dt>{{ __('Reference') }}</dt><dd>{{ $voucher->reference }}</dd>@endif
            @if ($voucher->attachment_path)<dt>{{ __('Attachment') }}</dt><dd><a href="{{ $voucher->attachmentUrl() }}" target="_blank" rel="noopener"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ __('Open') }}</a></dd>@endif
            <dt>{{ __('Written by') }}</dt><dd>{{ $voucher->creator?->name ?? '—' }}</dd>
            @if ($voucher->posted_at)<dt>{{ __('Posted') }}</dt><dd>{{ $voucher->poster?->name }} · {{ $voucher->posted_at->format('j M Y H:i') }}</dd>@endif
            @include('finance::partials.entry-link', ['record' => $voucher])
        </dl>
        @if ($voucher->journalEntry)<div class="mt-5">@include('finance::partials.entry-lines', ['entry' => $voucher->journalEntry])</div>@endif
        <x-slot:footer>
            <x-nit::button variant="light" :href="route('finance.'.$route.'.index')" icon="fas fa-arrow-left">{{ $title }}</x-nit::button>
            <button type="button" class="nx-btn nx-btn-light" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> {{ __('Print') }}</button>
            @can('update', $voucher)
                <form method="POST" action="{{ route("finance.{$route}.cancel", $voucher->id) }}" class="d-inline" data-nx-confirm="{{ __('Cancel this draft?') }}" data-nx-confirm-button="{{ __('Cancel draft') }}">@csrf<button class="nx-btn nx-btn-light" type="submit">{{ __('Cancel draft') }}</button></form>
                <x-nit::button variant="light" :href="route('finance.'.$route.'.edit', $voucher->id)" icon="fas fa-pen">{{ __('Edit') }}</x-nit::button>
            @endcan
            @can('post', $voucher)
                <form method="POST" action="{{ route("finance.{$route}.post", $voucher->id) }}" class="d-inline" data-nx-confirm="{{ __('Post :number?', ['number' => $voucher->number]) }}" data-nx-confirm-button="{{ __('Post') }}">@csrf<button class="nx-btn nx-btn-primary" type="submit"><i class="fas fa-check" aria-hidden="true"></i> {{ __('Post') }}</button></form>
            @endcan
            @can('reverse', $voucher)
                @include('finance::partials.reverse', ['action' => route("finance.{$route}.reverse", $voucher->id), 'label' => $voucher->number])
            @endcan
        </x-slot:footer>
    </x-nit::card>
</div>
@endsection
