{{-- Lines of a journal entry. @include('finance::partials.entry-lines', ['entry' => $entry]) --}}
<div class="table-responsive">
    <table class="nx-tbl">
        <thead>
            <tr><th>{{ __('Account') }}</th><th>{{ __('Detail') }}</th><th class="fin-num">{{ __('Debit') }}</th><th class="fin-num">{{ __('Credit') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($entry->lines as $line)
                <tr>
                    <td><a href="{{ route('finance.reports.ledger', ['account_id' => $line->account_id, 'from' => $entry->entry_date->toDateString(), 'to' => $entry->entry_date->toDateString()]) }}">{{ $line->account->label() }}</a></td>
                    <td class="nx-muted">
                        {{ $line->description }}
                        @if ($line->cash_bank_account_id && $line->relationLoaded('cashBankAccount'))<span class="d-block">{{ $line->cashBankAccount?->name }}</span>@endif
                        @if ($line->component)<span class="d-block fs-8 text-uppercase">{{ __($line->component->value) }}</span>@endif
                    </td>
                    <td class="fin-num">{{ $line->money('debit')->isZero() ? '' : $line->money('debit')->format() }}</td>
                    <td class="fin-num">{{ $line->money('credit')->isZero() ? '' : $line->money('credit')->format() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="fin-total"><th colspan="2">{{ __('Total') }}</th><th class="fin-num">{{ $entry->totalDebit()->format() }}</th><th class="fin-num">{{ $entry->totalCredit()->format() }}</th></tr>
        </tfoot>
    </table>
</div>
