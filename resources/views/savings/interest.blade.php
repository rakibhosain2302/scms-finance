@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Interest on savings'))
@section('nx-inline-errors', '1')
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="__('Interest on savings')" :subtitle="__('On the daily balance: balance × days × rate ÷ 365. Credited to each account as an expense of the samiti.')">
        <form method="GET" action="{{ route('finance.savings-interest.create') }}" class="nx-form-grid">
            <x-nit::select name="savings_scheme_id" :label="__('Scheme')" :options="$schemes->mapWithKeys(fn ($s) => [$s->id => $s->name.' · '.rtrim(rtrim($s->interest_rate, '0'), '.').'%'])->all()" :value="$scheme?->id" required :placeholder="__('Choose…')" />
            @if (count($branches) > 1)
                <x-nit::select name="branch_id" :label="__('Branch')" :options="$branches" :value="$branch" required />
            @else
                <input type="hidden" name="branch_id" value="{{ $branch }}">
            @endif
            <x-nit::input name="period_from" type="date" :label="__('From')" :value="$from->toDateString()" required />
            <x-nit::input name="period_to" type="date" :label="__('To')" :value="$to->toDateString()" required />
            <div class="nx-full"><button type="submit" class="nx-btn nx-btn-light"><i class="fas fa-calculator" aria-hidden="true"></i> {{ __('Calculate') }}</button></div>
        </form>
        @if ($schemes->isEmpty())
            <p class="nx-muted mt-4">{{ __('No scheme pays interest. Set a rate on a savings scheme first.') }}</p>
        @endif
    </x-nit::card>

    @if ($error)
        <div class="nx-alert mt-5" role="alert"><i class="fas fa-exclamation-circle mt-1" aria-hidden="true"></i> <span>{{ $error }}</span></div>
    @endif

    @if ($preview !== null)
        <x-nit::card :title="__('Preview')" :subtitle="__(':n accounts · :from – :to', ['n' => $preview->count(), 'from' => $from->format('j M Y'), 'to' => $to->format('j M Y')])" class="mt-5" :padding="false">
            @if ($preview->isEmpty())
                <x-nit::empty-state icon="fas fa-percent" :title="__('No account earned interest in this period')" />
            @else
                <div class="table-responsive"><table class="nx-tbl">
                    <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Member') }}</th><th class="fin-num">{{ __('Interest') }}</th></tr></thead>
                    <tbody>
                        @foreach ($preview as $row)
                            <tr><td>{{ $row['account']->account_number }}</td><td>{{ $members[$row['account']->member_id] ?? '—' }}</td><td class="fin-num">{{ $row['interest']->format() }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="fin-total"><th colspan="2">{{ __('Total') }}</th><th class="fin-num">{{ $previewTotal->format() }}</th></tr></tfoot>
                </table></div>
                <form method="POST" action="{{ route('finance.savings-interest.store') }}" class="d-flex flex-wrap gap-3 align-items-end p-5 border-top" data-nx-once
                      data-nx-confirm="{{ __('Post :total of interest on :n accounts?', ['total' => $previewTotal->format(), 'n' => $preview->count()]) }}" data-nx-confirm-button="{{ __('Post') }}">
                    @csrf
                    <input type="hidden" name="savings_scheme_id" value="{{ $scheme->id }}">
                    <input type="hidden" name="branch_id" value="{{ $branch }}">
                    <input type="hidden" name="period_from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="period_to" value="{{ $to->toDateString() }}">
                    <x-nit::input name="posting_date" type="date" :label="__('Posting date')" :value="today()->toDateString()" required />
                    <x-nit::button icon="fas fa-check">{{ __('Post interest') }}</x-nit::button>
                </form>
            @endif
        </x-nit::card>
    @endif

    <x-nit::card :title="__('Posted interest')" class="mt-5" :padding="false">
        @if ($runs->isEmpty())
            <x-nit::empty-state icon="fas fa-history" :title="__('No interest posted yet')" />
        @else
            <div class="table-responsive"><table class="nx-tbl">
                <thead><tr><th>{{ __('Scheme') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Period') }}</th><th>{{ __('Posted') }}</th><th class="fin-num">{{ __('Rate') }}</th><th class="fin-num">{{ __('Accounts') }}</th><th class="fin-num">{{ __('Total') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td>{{ $run->scheme?->name }}</td><td>{{ $run->branch?->name }}</td>
                            <td>{{ $run->period_from->format('j M Y') }} – {{ $run->period_to->format('j M Y') }}</td>
                            <td>{{ $run->posting_date->format('j M Y') }}</td>
                            <td class="fin-num">{{ rtrim(rtrim($run->rate, '0'), '.') }}%</td>
                            <td class="fin-num">{{ $run->accounts_count }}</td>
                            <td class="fin-num">@finmoney($run->total)</td>
                            <td>{{ $run->creator?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </x-nit::card>
</div>
@endsection
