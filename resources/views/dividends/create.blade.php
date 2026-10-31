@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Dividend on shares'))
@section('nx-inline-errors', '1')
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="__('Dividend on shares')" :subtitle="__('For a closed year: each member\'s share balance on its last day × the rate, credited to their savings account, out of the accumulated surplus.')">
        <form method="GET" action="{{ route('finance.dividends.create') }}" class="nx-form-grid">
            <x-nit::select name="financial_year_id" :label="__('Financial year (closed)')" :options="$years" :value="$year?->id" required :placeholder="__('Choose…')" />
            <x-nit::input name="rate" inputmode="decimal" :label="__('Dividend rate %')" :value="$rate" required />
            <x-nit::select name="savings_scheme_id" :label="__('Credit to accounts of')" :options="$schemes" :value="$scheme?->id" required :placeholder="__('Choose…')" />
            <div class="nx-full"><button type="submit" class="nx-btn nx-btn-light"><i class="fas fa-calculator" aria-hidden="true"></i> {{ __('Calculate') }}</button></div>
        </form>
        @if ($years === [])<p class="nx-muted mt-4 mb-0">{{ __('No financial year is closed yet.') }}</p>@endif
    </x-nit::card>

    @if ($error)<div class="nx-alert mt-5" role="alert"><i class="fas fa-exclamation-circle mt-1" aria-hidden="true"></i> <span>{{ $error }}</span></div>@endif

    @if ($preview)
        <x-nit::card :title="__('Preview')" :subtitle="__(':n members', ['n' => $preview['rows']->count()])" class="mt-5" :padding="false">
            @if ($preview['skipped'])
                <div class="nx-alert m-4" role="alert"><i class="fas fa-exclamation-triangle mt-1" aria-hidden="true"></i>
                    <span>{{ __('Left out, no active account in this scheme: :names', ['names' => collect($preview['skipped'])->map(fn ($id) => $members[$id] ?? '#'.$id)->join(', ')]) }}</span></div>
            @endif
            @if ($preview['rows']->isNotEmpty())
                <div class="table-responsive"><table class="nx-tbl">
                    <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Account') }}</th><th class="fin-num">{{ __('Shares') }}</th><th class="fin-num">{{ __('Dividend') }}</th></tr></thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr><td>{{ $members[$row['member_id']] ?? '#'.$row['member_id'] }}</td><td>{{ $row['account']->account_number }}</td><td class="fin-num">{{ $row['shares']->format() }}</td><td class="fin-num">{{ $row['dividend']->format() }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="fin-total"><th colspan="3">{{ __('Total') }}</th><th class="fin-num">{{ \SCMS\Finance\Support\Money::sum($preview['rows']->pluck('dividend')->all())->format() }}</th></tr></tfoot>
                </table></div>
                <form method="POST" action="{{ route('finance.dividends.store') }}" class="d-flex flex-wrap gap-3 align-items-end p-5 border-top" data-nx-once data-nx-confirm="{{ __('Credit the dividend?') }}" data-nx-confirm-button="{{ __('Post') }}">
                    @csrf
                    <input type="hidden" name="financial_year_id" value="{{ $year->id }}">
                    <input type="hidden" name="savings_scheme_id" value="{{ $scheme->id }}">
                    <input type="hidden" name="rate" value="{{ $rate }}">
                    <x-nit::input name="posting_date" type="date" :label="__('Posting date')" :value="today()->toDateString()" required />
                    <x-nit::button icon="fas fa-check">{{ __('Post dividend') }}</x-nit::button>
                </form>
            @endif
        </x-nit::card>
    @endif

    <x-nit::card :title="__('Dividends posted')" class="mt-5" :padding="false">
        @if ($runs->isEmpty())
            <x-nit::empty-state icon="fas fa-history" :title="__('No dividend posted yet')" />
        @else
            <div class="table-responsive"><table class="nx-tbl">
                <thead><tr><th>{{ __('Year') }}</th><th>{{ __('Scheme') }}</th><th class="fin-num">{{ __('Rate') }}</th><th>{{ __('Posted') }}</th><th class="fin-num">{{ __('Accounts') }}</th><th class="fin-num">{{ __('Total') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr><td>{{ $run->year?->name }}</td><td>{{ $run->scheme?->name }}</td><td class="fin-num">{{ rtrim(rtrim($run->rate, '0'), '.') }}%</td><td>{{ $run->posting_date->format('j M Y') }}</td><td class="fin-num">{{ $run->accounts_count }}</td><td class="fin-num">@finmoney($run->total)</td><td>{{ $run->creator?->name ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </x-nit::card>
</div>
@endsection
