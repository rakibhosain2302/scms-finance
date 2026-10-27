@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $account ? __('Ledger · :account', ['account' => $account->label()]) : __('Ledger'))
@include('finance::partials.styles')

@section('report-filters')
    <select name="account_id" class="form-select nx-filter-select" aria-label="{{ __('Account') }}" required>
        <option value="">{{ __('Choose an account') }}</option>
        @foreach ($accounts as $v => $l)<option value="{{ $v }}" @selected($account?->id === $v)>{{ $l }}</option>@endforeach
    </select>
@endsection

@section('content')
<div class="nx-page">
    <x-nit::card :title="$account ? $account->label() : __('Ledger')" :subtitle="$account ? $account->type->label().' · '.$branchLabel.' · '.$from->format('j M Y').' – '.$to->format('j M Y') : __('Choose an account.')" :padding="false">
        @include('finance::partials.period-filters')
        @if ($ledger)
            <div class="table-responsive">
                <table class="nx-tbl">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Particulars') }}</th><th class="fin-num">{{ __('Debit') }}</th><th class="fin-num">{{ __('Credit') }}</th><th class="fin-num">{{ __('Balance') }}</th></tr></thead>
                    <tbody>
                        <tr class="nx-muted"><td colspan="5">{{ __('Brought forward') }}</td><td class="fin-num">{{ $ledger['opening']->format() }}</td></tr>
                        @forelse ($ledger['rows'] as $row)
                            @php($dr = \SCMS\Finance\Support\Money::of($row->debit))
                            @php($cr = \SCMS\Finance\Support\Money::of($row->credit))
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->entry_date)->format('j M Y') }}</td>
                                <td><a href="{{ route('finance.journals.show', $row->journal_entry_id) }}">{{ $row->number }}</a></td>
                                <td>{{ \Illuminate\Support\Str::limit($row->entry_description, 70) }}@if ($row->account_id !== $account->id)<span class="nx-muted fs-7 d-block">{{ $row->account_code }} · {{ $row->account_name }}</span>@endif @if ($row->reversal_of_id)<x-nit::badge tone="danger">{{ __('Reversal') }}</x-nit::badge>@endif</td>
                                <td class="fin-num">{{ $dr->isZero() ? '' : $dr->format() }}</td>
                                <td class="fin-num">{{ $cr->isZero() ? '' : $cr->format() }}</td>
                                <td @class(['fin-num', 'fin-neg' => $row->balance->isNegative()])>{{ $row->balance->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="nx-muted text-center py-5">{{ __('No postings in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="fin-total"><th colspan="3">{{ __('Total / closing balance') }}</th><th class="fin-num">{{ $ledger['debit']->format() }}</th><th class="fin-num">{{ $ledger['credit']->format() }}</th><th class="fin-num">{{ $ledger['closing']->format() }}</th></tr></tfoot>
                </table>
            </div>
        @endif
    </x-nit::card>
</div>
@endsection
