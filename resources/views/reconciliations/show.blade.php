@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Reconciliation · :account', ['account' => $reconciliation->cashBankAccount->label()]))
@include('finance::partials.styles')

@section('content')
@php
    $draft = $reconciliation->isDraft();
    // Cleared by earlier reconciliations: the ticks on this page add to it.
    $before = $summary['cleared']->minus(\SCMS\Finance\Support\Money::sum($lines->whereIn('id', $ticked)->map(fn ($l) => \SCMS\Finance\Support\Money::of($l->debit)->minus($l->credit))->all()));
@endphp
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$reconciliation->cashBankAccount->label()" :subtitle="__('Statement of :date', ['date' => $reconciliation->statement_date->format('j M Y')])">
        <x-slot:actions>@if ($draft)<x-nit::badge tone="warning">{{ __('Draft') }}</x-nit::badge>@else<x-nit::badge tone="success">{{ __('Completed') }}</x-nit::badge>@endif</x-slot:actions>
        <div class="fin-tiles" data-fin-recon data-before="{{ $before->toString() }}" data-statement="{{ $summary['statement']->toString() }}">
            <div class="fin-tile"><small>{{ __('Statement balance') }}</small><strong>{{ $summary['statement']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Cleared') }}</small><strong data-fin-cleared>{{ $summary['cleared']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Difference') }}</small><strong data-fin-difference @class(['fin-neg' => ! $summary['difference']->isZero()])>{{ $summary['difference']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Book balance (ledger)') }}</small><strong>{{ $summary['book']->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('In transit (book − cleared)') }}</small><strong>{{ $summary['uncleared']->format() }}</strong></div>
        </div>
        @unless ($draft)
            <p class="nx-muted mt-4 mb-0">{{ __('Completed :date by :name.', ['date' => $reconciliation->completed_at?->format('j M Y H:i'), 'name' => $reconciliation->completer?->name ?? '—']) }}</p>
        @endunless
    </x-nit::card>

    <form method="POST" action="{{ route('finance.reconciliations.update', $reconciliation) }}" data-nx-once>
        @csrf @method('PUT')
        <x-nit::card :title="$draft ? __('Tick what the bank statement shows') : __('Cleared lines')" class="mt-5" :padding="false">
            @if ($lines->isEmpty())
                <x-nit::empty-state icon="fas fa-check-double" :title="__('No ledger lines up to the statement date')" />
            @else
                <div class="table-responsive"><table class="nx-tbl">
                    <thead><tr>@if ($draft)<th style="width:2.5rem"><input type="checkbox" class="form-check-input" data-fin-all aria-label="{{ __('Tick all') }}"></th>@endif<th>{{ __('Date') }}</th><th>{{ __('Number') }}</th><th>{{ __('Description') }}</th><th class="fin-num">{{ __('In') }}</th><th class="fin-num">{{ __('Out') }}</th></tr></thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                @if ($draft)<td><input type="checkbox" class="form-check-input" name="lines[]" value="{{ $line->id }}" data-net="{{ \SCMS\Finance\Support\Money::of($line->debit)->minus($line->credit)->toString() }}" @checked(in_array($line->id, $ticked, true)) aria-label="{{ __('Cleared') }}"></td>@endif
                                <td>{{ $line->entry?->entry_date?->format('j M Y') }}</td>
                                <td>{{ $line->entry?->number }}</td>
                                <td>{{ $line->description ?: $line->entry?->description }}</td>
                                <td class="fin-num">@if (\SCMS\Finance\Support\Money::of($line->debit)->isPositive()) @finmoney($line->debit) @endif</td>
                                <td class="fin-num">@if (\SCMS\Finance\Support\Money::of($line->credit)->isPositive()) @finmoney($line->credit) @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.reconciliations.index')" icon="fas fa-arrow-left">{{ __('Reconciliations') }}</x-nit::button>
                @if ($draft)
                    <button type="submit" class="nx-btn nx-btn-light"><i class="fas fa-save" aria-hidden="true"></i> {{ __('Save ticks') }}</button>
                    <button type="submit" name="complete" value="1" class="nx-btn nx-btn-primary"><i class="fas fa-check" aria-hidden="true"></i> {{ __('Complete') }}</button>
                @endif
            </x-slot:footer>
        </x-nit::card>
    </form>

    @if ($draft)
        <form method="POST" action="{{ route('finance.reconciliations.destroy', $reconciliation) }}" class="mt-3" data-nx-confirm="{{ __('Delete this unfinished reconciliation?') }}" data-nx-confirm-button="{{ __('Delete') }}">
            @csrf @method('DELETE')
            <button type="submit" class="nx-btn nx-btn-light"><i class="fas fa-trash" aria-hidden="true"></i> {{ __('Delete draft') }}</button>
        </form>
    @endif
</div>
@endsection

@if ($draft)
@push('js')
<script>
    (function () {
        var box = document.querySelector('[data-fin-recon]');
        if (!box) return;
        // Amounts in paisa (integers) so the sum stays exact.
        var paisa = function (s) { var neg = s.charAt(0) === '-', p = s.replace('-', '').split('.'); var v = parseInt(p[0], 10) * 100 + parseInt(((p[1] || '') + '00').slice(0, 2), 10); return neg ? -v : v; };
        var show = function (v) { var neg = v < 0; v = Math.abs(v); var s = Math.floor(v / 100).toLocaleString('en-US') + '.' + String(v % 100).padStart(2, '0'); return (neg ? '-' : '') + s; };
        var before = paisa(box.dataset.before), statement = paisa(box.dataset.statement);
        var boxes = document.querySelectorAll('input[name="lines[]"]');
        var render = function () {
            var cleared = before;
            boxes.forEach(function (b) { if (b.checked) cleared += paisa(b.dataset.net); });
            box.querySelector('[data-fin-cleared]').textContent = show(cleared);
            var diff = box.querySelector('[data-fin-difference]');
            diff.textContent = show(statement - cleared);
            diff.classList.toggle('fin-neg', statement !== cleared);
        };
        boxes.forEach(function (b) { b.addEventListener('change', render); });
        var all = document.querySelector('[data-fin-all]');
        if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); render(); });
    })();
</script>
@endpush
@endif
