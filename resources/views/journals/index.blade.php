@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Journal'))
@include('finance::partials.styles')

@can(\SCMS\Finance\Support\Abilities::JOURNAL_CREATE)
@push('buttons')
<a href="{{ route('finance.journals.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('New journal entry') }}</a>
@endpush
@endcan

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$entries" :title="__('Journal entries')" :subtitle="__('Every posting of every module. Entries of documents change only through their document.')" search
        :columns="['number' => __('Number'), 'date' => __('Date'), 'description' => __('Description'), 'source' => __('Source'), 'total' => ['label' => __('Amount'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('No entries yet')" empty-icon="fas fa-book">
        <x-slot:filters>
            <select name="status" class="form-select nx-filter-select" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
            <select name="source" class="form-select nx-filter-select" aria-label="{{ __('Source') }}"><option value="">{{ __('Any source') }}</option>@foreach ($sources as $v => $l)<option value="{{ $v }}" @selected(request('source') === $v)>{{ $l }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="{{ __('From') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="{{ __('To') }}">
        </x-slot:filters>
        @foreach ($entries as $entry)
            <tr>
                <td><a href="{{ route('finance.journals.show', $entry) }}">{{ $entry->number }}</a></td>
                <td>{{ $entry->entry_date->format('j M Y') }}</td>
                <td>{{ \Illuminate\Support\Str::limit($entry->description, 70) }}<span class="nx-muted fs-7 d-block">{{ $entry->branch?->name }}</span></td>
                <td class="nx-muted">{{ __(ucfirst(str_replace('_', ' ', $entry->source_type))) }}@if ($entry->reversal_of_id) · {{ __('reversal') }}@endif</td>
                <td class="fin-num">@finmoney($entry->total)</td>
                <td>@include('finance::partials.status', ['status' => $entry->status])</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
