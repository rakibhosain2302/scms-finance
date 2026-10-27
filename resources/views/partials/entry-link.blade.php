{{-- Link to the journal entry of a document, and to its reversal. --}}
@if ($record->journalEntry)
    <dt>{{ __('Journal entry') }}</dt>
    <dd>@can('view', $record->journalEntry)<a href="{{ route('finance.journals.show', $record->journalEntry) }}">{{ $record->journalEntry->number }}</a>@else{{ $record->journalEntry->number }}@endcan</dd>
@endif
@if ($record->reversalEntry)
    <dt>{{ __('Reversed by') }}</dt>
    <dd>@can('view', $record->reversalEntry)<a href="{{ route('finance.journals.show', $record->reversalEntry) }}">{{ $record->reversalEntry->number }}</a>@else{{ $record->reversalEntry->number }}@endcan
        <span class="d-block nx-muted fs-7">{{ $record->reversalEntry->reversal_reason }}</span></dd>
@endif
