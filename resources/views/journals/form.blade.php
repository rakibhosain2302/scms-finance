@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $entry->exists ? __('Edit :number', ['number' => $entry->number]) : __('New journal entry'))
@section('nx-inline-errors', '1')
@include('finance::partials.styles')

@php
    $lines = old('lines', $entry->exists ? $entry->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->money('debit')->isZero() ? '' : $l->debit, 'credit' => $l->money('credit')->isZero() ? '' : $l->credit, 'description' => $l->description, 'cash_bank_account_id' => $l->cash_bank_account_id])->all() : []);
    $lines = array_pad(array_values($lines), max(4, count($lines) + 1), []);
@endphp

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ $entry->exists ? route('finance.journals.update', $entry) : route('finance.journals.store') }}" data-nx-once novalidate id="fin-journal">
        @csrf
        @if ($entry->exists) @method('PUT') @endif
        <x-nit::card :title="$entry->exists ? $entry->number : __('New journal entry')" :subtitle="__('Debits must equal credits. Member savings and loans move only through their own screens.')">
            <div class="nx-form-grid">
                @unless ($entry->exists)
                    @include('finance::partials.branch-picker')
                @endunless
                <x-nit::input name="entry_date" type="date" :label="__('Date')" :value="$entry->entry_date?->toDateString()" required />
                <x-nit::input name="description" :label="__('Narration')" :value="$entry->description" required maxlength="500" wrapper-class="nx-full" />
                <x-nit::input name="reference" :label="__('Reference')" :value="$entry->reference" maxlength="100" />
            </div>

            <div class="table-responsive mt-5">
                <table class="nx-tbl fin-lines">
                    <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Cash / bank account') }}</th><th class="fin-num">{{ __('Debit') }}</th><th class="fin-num">{{ __('Credit') }}</th><th>{{ __('Line note') }}</th></tr></thead>
                    <tbody>
                        @foreach ($lines as $i => $line)
                            <tr>
                                <td>
                                    <select name="lines[{{ $i }}][account_id]" class="form-select" aria-label="{{ __('Account of line :n', ['n' => $i + 1]) }}">
                                        <option value="">—</option>
                                        @foreach ($accounts as $a)
                                            <option value="{{ $a->id }}" @selected((string) ($line['account_id'] ?? '') === (string) $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="lines[{{ $i }}][cash_bank_account_id]" class="form-select" aria-label="{{ __('Cash or bank account of line :n', ['n' => $i + 1]) }}">
                                        <option value="">—</option>
                                        @foreach ($cashAccounts as $c)
                                            <option value="{{ $c->id }}" @selected((string) ($line['cash_bank_account_id'] ?? '') === (string) $c->id)>{{ $c->name }} ({{ $c->branch?->name }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" inputmode="decimal" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? '' }}" class="form-control text-end fin-debit" aria-label="{{ __('Debit of line :n', ['n' => $i + 1]) }}"></td>
                                <td><input type="text" inputmode="decimal" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? '' }}" class="form-control text-end fin-credit" aria-label="{{ __('Credit of line :n', ['n' => $i + 1]) }}"></td>
                                <td><input type="text" name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}" class="form-control" maxlength="500" aria-label="{{ __('Note of line :n', ['n' => $i + 1]) }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fin-total"><th colspan="2">{{ __('Total') }} <span id="fin-diff" class="ms-2 fs-7"></span></th><th class="fin-num" id="fin-total-debit">0.00</th><th class="fin-num" id="fin-total-credit">0.00</th><th></th></tr>
                    </tfoot>
                </table>
            </div>
            <p class="nx-hint">{{ __('Leave unused rows empty. Save first to get more rows.') }}</p>

            <x-slot:footer>
                <x-nit::button variant="light" :href="$entry->exists ? route('finance.journals.show', $entry) : route('finance.journals.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button variant="light" icon="fas fa-save" name="post" value="0">{{ __('Save draft') }}</x-nit::button>
                @if ($canPost)<x-nit::button icon="fas fa-check" name="post" value="1">{{ __('Save and post') }}</x-nit::button>@endif
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection

@push('js')
<script>
    // Totals while typing, in cents so no rounding shows up; the server checks again.
    (function () {
        var form = document.getElementById('fin-journal');
        var cents = function (v) { v = String(v || '').replace(/,/g, '').trim(); if (!/^\d+(\.\d{0,2})?$/.test(v)) return 0; var p = v.split('.'); return parseInt(p[0], 10) * 100 + parseInt(((p[1] || '') + '00').slice(0, 2), 10); };
        var fmt = function (c) { return (c / 100).toFixed(2); };
        var update = function () {
            var d = 0, c = 0;
            form.querySelectorAll('.fin-debit').forEach(function (i) { d += cents(i.value); });
            form.querySelectorAll('.fin-credit').forEach(function (i) { c += cents(i.value); });
            document.getElementById('fin-total-debit').textContent = fmt(d);
            document.getElementById('fin-total-credit').textContent = fmt(c);
            var diff = document.getElementById('fin-diff');
            diff.textContent = d === c ? (d ? '✓' : '') : '{{ __('Difference') }}: ' + fmt(Math.abs(d - c));
            diff.className = 'ms-2 fs-7 ' + (d === c ? 'text-success' : 'text-danger');
        };
        form.addEventListener('input', update);
        update();
    })();
</script>
@endpush
