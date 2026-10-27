@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $transaction->type->label().' '.$transaction->number)
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$transaction->number" :subtitle="$transaction->type->label().' · '.$member">
        <x-slot:actions>@include('finance::partials.status', ['status' => $transaction->status])</x-slot:actions>
        <dl class="fin-facts">
            <dt>{{ __('Date') }}</dt><dd>{{ $transaction->transaction_date->format('j M Y') }}</dd>
            <dt>{{ __('Account') }}</dt><dd><a href="{{ route('finance.savings-accounts.show', $transaction->savingsAccount) }}">{{ $transaction->savingsAccount->account_number }}</a> · {{ $transaction->savingsAccount->scheme->name }}</dd>
            <dt>{{ __('Amount') }}</dt><dd><strong>@finmoney($transaction->amount)</strong></dd>
            <dt>{{ __('Cash / bank') }}</dt><dd>{{ $transaction->cashBankAccount?->label() }}</dd>
            <dt>{{ __('Branch') }}</dt><dd>{{ $transaction->branch?->name }}</dd>
            @if ($transaction->reference)<dt>{{ __('Reference') }}</dt><dd>{{ $transaction->reference }}</dd>@endif
            @if ($transaction->description)<dt>{{ __('Note') }}</dt><dd>{{ $transaction->description }}</dd>@endif
            <dt>{{ __('Posted by') }}</dt><dd>{{ $transaction->creator?->name ?? '—' }} · {{ $transaction->posted_at?->format('j M Y H:i') }}</dd>
            @include('finance::partials.entry-link', ['record' => $transaction])
        </dl>
        @if ($transaction->journalEntry)<div class="mt-5">@include('finance::partials.entry-lines', ['entry' => $transaction->journalEntry])</div>@endif
        <x-slot:footer>
            <x-nit::button variant="light" :href="route('finance.savings-accounts.show', $transaction->savingsAccount)" icon="fas fa-arrow-left">{{ __('Account') }}</x-nit::button>
            <button type="button" class="nx-btn nx-btn-light" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> {{ __('Print receipt') }}</button>
            @can('reverse', $transaction)
                @include('finance::partials.reverse', ['action' => route('finance.savings-transactions.reverse', $transaction), 'label' => $transaction->number])
            @endcan
        </x-slot:footer>
    </x-nit::card>
</div>
@endsection
