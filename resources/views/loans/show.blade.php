@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Loan :number', ['number' => $loan->loan_number]))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$loan->loan_number" :subtitle="$member.' · '.$loan->product->name">
        <x-slot:actions>@include('finance::partials.status', ['status' => $loan->status])</x-slot:actions>
        <div class="fin-tiles mb-5">
            <div class="fin-tile"><small>{{ __('Principal') }}</small><strong>@finmoney($loan->principal)</strong></div>
            <div class="fin-tile"><small>{{ __('Interest (schedule)') }}</small><strong>@finmoney($loan->total_interest)</strong></div>
            <div class="fin-tile"><small>{{ __('Principal outstanding (ledger)') }}</small><strong>{{ $outstanding->format() }}</strong></div>
            @php($overdue = $loan->installments->filter->isOverdue())
            <div class="fin-tile"><small>{{ __('Overdue') }}</small><strong @class(['fin-neg' => $overdue->isNotEmpty()])>{{ \SCMS\Finance\Support\Money::sum($overdue->map->remaining())->format() }}</strong><span class="nx-muted fs-7">{{ trans_choice(':count installment|:count installments', $overdue->count(), ['count' => $overdue->count()]) }}</span></div>
        </div>
        <dl class="fin-facts">
            <dt>{{ __('Branch') }}</dt><dd>{{ $loan->branch?->name }}</dd>
            <dt>{{ __('Terms') }}</dt><dd>{{ $loan->interest_method->label() }}@if ($loan->interest_method !== \SCMS\Finance\Enums\InterestMethod::None) {{ rtrim(rtrim($loan->interest_rate, '0'), '.') }}% {{ __('a year') }}@endif · {{ $loan->installments_count }} {{ strtolower($loan->repayment_frequency->label()) }}</dd>
            <dt>{{ __('Processing fee') }}</dt><dd>@finmoney($loan->processing_fee)</dd>
            <dt>{{ __('Applied') }}</dt><dd>{{ $loan->applied_on->format('j M Y') }} · {{ $loan->creator?->name }}</dd>
            @if ($loan->approved_at)<dt>{{ __('Approved') }}</dt><dd>{{ $loan->approved_at->format('j M Y') }} · {{ $loan->approver?->name }}</dd>@endif
            @if ($loan->disbursed_on)<dt>{{ __('Disbursed') }}</dt><dd>{{ $loan->disbursed_on->format('j M Y') }} · {{ $loan->disbursementAccount?->name }}@if ($loan->disbursementEntry) · <a href="{{ route('finance.journals.show', $loan->disbursementEntry) }}">{{ $loan->disbursementEntry->number }}</a>@endif</dd>@endif
            @if ($loan->closed_on)<dt>{{ __('Closed') }}</dt><dd>{{ $loan->closed_on->format('j M Y') }}</dd>@endif
            @if ($loan->purpose)<dt>{{ __('Purpose') }}</dt><dd>{{ $loan->purpose }}</dd>@endif
            @if ($loan->decision_note)<dt>{{ __('Note') }}</dt><dd>{{ $loan->decision_note }}</dd>@endif
        </dl>
        <x-slot:footer>
            <x-nit::button variant="light" :href="route('finance.loans.index')" icon="fas fa-arrow-left">{{ __('Loans') }}</x-nit::button>
            @can('cancel', $loan)
                @include('finance::partials.reverse', ['action' => route('finance.loans.cancel', $loan), 'label' => $loan->loan_number, 'button' => __('Cancel'), 'title' => __('Cancel the application'), 'text' => __('The application stays on record as cancelled.'), 'confirm' => __('Cancel application')])
            @endcan
            @can('approve', $loan)
                @include('finance::partials.reverse', ['action' => route('finance.loans.reject', $loan), 'label' => $loan->loan_number, 'button' => __('Reject'), 'title' => __('Reject the application'), 'text' => __('The member is told the reason.'), 'confirm' => __('Reject')])
                <form method="POST" action="{{ route('finance.loans.approve', $loan) }}" class="d-inline" data-nx-confirm="{{ __('Approve :number?', ['number' => $loan->loan_number]) }}" data-nx-confirm-button="{{ __('Approve') }}">@csrf<button type="submit" class="nx-btn nx-btn-primary"><i class="fas fa-check" aria-hidden="true"></i> {{ __('Approve') }}</button></form>
            @endcan
            @can('disburse', $loan)
                <x-nit::button :href="route('finance.loans.disburse.create', $loan)" icon="fas fa-money-bill-wave">{{ __('Disburse') }}</x-nit::button>
            @endcan
            @can('reverseDisbursement', $loan)
                @include('finance::partials.reverse', ['action' => route('finance.loans.reverse-disbursement', $loan), 'label' => $loan->loan_number, 'button' => __('Reverse disbursement'), 'text' => __('Only possible before any repayment. The disbursement entry is reversed and the loan cancelled.')])
            @endcan
            @can('repay', $loan)
                <x-nit::button :href="route('finance.loan-repayments.create', $loan)" icon="fas fa-hand-holding-usd">{{ __('Take repayment') }}</x-nit::button>
            @endcan
        </x-slot:footer>
    </x-nit::card>

    @if ($loan->installments->isNotEmpty())
    <x-nit::card :title="__('Installments')" class="mt-5" :padding="false">
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>#</th><th>{{ __('Due') }}</th><th class="fin-num">{{ __('Principal') }}</th><th class="fin-num">{{ __('Interest') }}</th><th class="fin-num">{{ __('Paid') }}</th><th class="fin-num">{{ __('Remaining') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach ($loan->installments as $i)
                        <tr>
                            <td>{{ $i->installment_no }}</td>
                            <td>{{ $i->due_date->format('j M Y') }}</td>
                            <td class="fin-num">@finmoney($i->principal_due)</td>
                            <td class="fin-num">@finmoney($i->interest_due)</td>
                            <td class="fin-num">{{ $i->totalPaid()->format() }}</td>
                            <td class="fin-num">{{ $i->remaining()->format() }}</td>
                            <td>@include('finance::partials.status', ['status' => $i->status]) @if ($i->isOverdue())<x-nit::badge tone="danger">{{ __('Overdue') }}</x-nit::badge>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fin-total"><th colspan="2">{{ __('Total') }}</th>
                        <th class="fin-num">{{ \SCMS\Finance\Support\Money::sum($loan->installments->pluck('principal_due'))->format() }}</th>
                        <th class="fin-num">{{ \SCMS\Finance\Support\Money::sum($loan->installments->pluck('interest_due'))->format() }}</th>
                        <th class="fin-num">{{ \SCMS\Finance\Support\Money::sum($loan->installments->map->totalPaid())->format() }}</th>
                        <th class="fin-num">{{ \SCMS\Finance\Support\Money::sum($loan->installments->map->remaining())->format() }}</th><th></th></tr>
                </tfoot>
            </table>
        </div>
    </x-nit::card>
    @endif

    @if ($loan->repayments->isNotEmpty())
    <x-nit::card :title="__('Repayments')" class="mt-5" :padding="false">
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th class="fin-num">{{ __('Amount') }}</th><th class="fin-num">{{ __('Principal') }}</th><th class="fin-num">{{ __('Interest') }}</th><th class="fin-num">{{ __('Penalty') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach ($loan->repayments as $r)
                        <tr>
                            <td><a href="{{ route('finance.loan-repayments.show', $r) }}">{{ $r->number }}</a></td>
                            <td>{{ $r->repayment_date->format('j M Y') }}</td>
                            <td class="fin-num">@finmoney($r->amount)</td>
                            <td class="fin-num">@finmoney($r->principal_amount)</td>
                            <td class="fin-num">@finmoney($r->interest_amount)</td>
                            <td class="fin-num">@finmoney($r->penalty_amount)</td>
                            <td>@include('finance::partials.status', ['status' => $r->status])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-nit::card>
    @endif
</div>
@endsection
