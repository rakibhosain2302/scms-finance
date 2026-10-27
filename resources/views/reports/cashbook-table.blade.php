{{-- Cash book rows. $book from ReportService::cashbook(); $showAccount adds the drawer / bank column. --}}
<div class="table-responsive">
    <table class="nx-tbl">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Particulars') }}</th>
                @if ($showAccount ?? true)<th>{{ __('Account') }}</th>@endif
                <th class="fin-num">{{ __('Receipt') }}</th><th class="fin-num">{{ __('Payment') }}</th><th class="fin-num">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="nx-muted"><td colspan="{{ ($showAccount ?? true) ? 6 : 5 }}">{{ __('Brought forward') }}</td><td class="fin-num">{{ $book['opening']->format() }}</td></tr>
            @forelse ($book['rows'] as $row)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row->entry_date)->format('j M Y') }}</td>
                    <td><a href="{{ route('finance.journals.show', $row->entry_id) }}">{{ $row->number }}</a></td>
                    <td>{{ \Illuminate\Support\Str::limit($row->description, 80) }}@if ($row->reversal_of_id) <x-nit::badge tone="danger">{{ __('Reversal') }}</x-nit::badge>@endif</td>
                    @if ($showAccount ?? true)<td class="nx-muted">{{ $row->cash_account }}</td>@endif
                    @php($in = \SCMS\Finance\Support\Money::of($row->debit))
                    @php($out = \SCMS\Finance\Support\Money::of($row->credit))
                    <td class="fin-num">{{ $in->isZero() ? '' : $in->format() }}</td>
                    <td class="fin-num">{{ $out->isZero() ? '' : $out->format() }}</td>
                    <td @class(['fin-num', 'fin-neg' => $row->balance->isNegative()])>{{ $row->balance->format() }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ ($showAccount ?? true) ? 7 : 6 }}" class="nx-muted text-center py-5">{{ __('Nothing received or paid in this period.') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="fin-total">
                <th colspan="{{ ($showAccount ?? true) ? 4 : 3 }}">{{ __('Total / carried forward') }}</th>
                <th class="fin-num">{{ $book['receipts']->format() }}</th><th class="fin-num">{{ $book['payments']->format() }}</th><th class="fin-num">{{ $book['closing']->format() }}</th>
            </tr>
        </tfoot>
    </table>
</div>
