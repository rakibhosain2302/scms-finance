@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Repayment :number', ['number' => $repayment->number]))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$repayment->number" :subtitle="$repayment->loan->loan_number.' · '.$member">
        <x-slot:actions>@include('finance::partials.status', ['status' => $repayment->status])</x-slot:actions>
        <dl class="fin-facts">
            <dt>{{ __('Date') }}</dt><dd>{{ $repayment->repayment_date->format('j M Y') }}</dd>
            <dt>{{ __('Loan') }}</dt><dd><a href="{{ route('finance.loans.show', $repayment->loan) }}">{{ $repayment->loan->loan_number }}</a></dd>
            <dt>{{ __('Amount') }}</dt><dd><strong>@finmoney($repayment->amount)</strong></dd>
            <dt>{{ __('Principal') }}</dt><dd>@finmoney($repayment->principal_amount)</dd>
            <dt>{{ __('Interest') }}</dt><dd>@finmoney($repayment->interest_amount)</dd>
            <dt>{{ __('Penalty') }}</dt><dd>@finmoney($repayment->penalty_amount)</dd>
            <dt>{{ __('Received into') }}</dt><dd>{{ $repayment->cashBankAccount?->label() }}</dd>
            @if ($repayment->reference)<dt>{{ __('Reference') }}</dt><dd>{{ $repayment->reference }}</dd>@endif
            <dt>{{ __('Posted by') }}</dt><dd>{{ $repayment->creator?->name ?? '—' }} · {{ $repayment->posted_at?->format('j M Y H:i') }}</dd>
            @include('finance::partials.entry-link', ['record' => $repayment])
        </dl>
        <h3 class="fs-6 mt-5">{{ __('Installments paid') }}</h3>
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>#</th><th>{{ __('Due') }}</th><th class="fin-num">{{ __('Principal') }}</th><th class="fin-num">{{ __('Interest') }}</th></tr></thead>
                <tbody>
                    @foreach ($repayment->allocations as $a)
                        <tr><td>{{ $a->installment->installment_no }}</td><td>{{ $a->installment->due_date->format('j M Y') }}</td><td class="fin-num">@finmoney($a->principal)</td><td class="fin-num">@finmoney($a->interest)</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($repayment->journalEntry)<div class="mt-5">@include('finance::partials.entry-lines', ['entry' => $repayment->journalEntry])</div>@endif
        <x-slot:footer>
            <x-nit::button variant="light" :href="route('finance.loans.show', $repayment->loan)" icon="fas fa-arrow-left">{{ __('Loan') }}</x-nit::button>
            <button type="button" class="nx-btn nx-btn-light" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> {{ __('Print receipt') }}</button>
            @can('reverse', $repayment)
                @include('finance::partials.reverse', ['action' => route('finance.loan-repayments.reverse', $repayment), 'label' => $repayment->number, 'text' => __('Only the latest repayment of a loan can be reversed. The installments get back what it paid.')])
            @endcan
        </x-slot:footer>
    </x-nit::card>
</div>
@endsection
