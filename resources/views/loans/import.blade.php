@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Bring in running loans'))
@section('nx-inline-errors', '1')
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')

    @if ($report)
        <x-nit::card :title="$report['dry_run'] ? __('Check result (nothing was saved)') : __('Import result')" class="mb-5">
            <p class="mb-2">{{ $report['dry_run'] ? __(':n rows are ready to import.', ['n' => $report['created']]) : __(':n loans brought in.', ['n' => $report['created']]) }}</p>
            @if ($report['errors'])
                <ul class="mb-0">@foreach ($report['errors'] as $e)<li>{{ __('Line :line: :reason', ['line' => $e['line'], 'reason' => $e['reason']]) }}</li>@endforeach</ul>
            @endif
        </x-nit::card>
    @endif

    <form method="POST" action="{{ route('finance.loans.import.store') }}" data-nx-once novalidate>
        @csrf
        <x-nit::card :title="__('Bring in a running loan')" :subtitle="__('For loans already running when the samiti starts using the system: what the member still owes is booked against opening balance equity (no cash moves), and the installments left are scheduled with the product\'s terms.')">
            <div class="nx-form-grid">
                @include('finance::partials.branch-picker')
                @include('finance::savings.member-picker')
                <x-nit::select name="loan_product_id" :label="__('Product')" :options="$products" required :placeholder="__('Choose…')" />
                <x-nit::input name="outstanding_principal" inputmode="decimal" :label="__('Principal still owed')" required />
                <x-nit::input name="installments_count" type="number" min="1" :label="__('Installments left')" required />
                <x-nit::input name="next_due_on" type="date" :label="__('Next installment due')" required />
                <x-nit::input name="opened_on" type="date" :label="__('Go-live date')" :value="today()->toDateString()" required />
                <x-nit::input name="original_principal" inputmode="decimal" :label="__('Amount originally lent')" :hint="__('Optional, for the record')" />
                <x-nit::input name="reference" :label="__('Old loan number')" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.loans.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Bring in') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>

    <form method="POST" action="{{ route('finance.loans.import.csv') }}" enctype="multipart/form-data" class="mt-5" data-nx-once>
        @csrf
        <x-nit::card :title="__('Many loans from a CSV file')" :subtitle="__('First row: :columns. Dates as YYYY-MM-DD. Up to 2 MB.', ['columns' => implode(', ', $columns)])">
            <div class="d-flex flex-wrap gap-3 align-items-end">
                <div><label for="fin-csv" class="form-label">{{ __('CSV file') }}</label><input id="fin-csv" type="file" name="file" accept=".csv,text/csv" class="form-control @error('file') is-invalid @enderror" required>@error('file')<p class="nx-error">{{ $message }}</p>@enderror</div>
                <label class="nx-check"><input type="checkbox" name="dry_run" value="1" checked> <span>{{ __('Check only (save nothing)') }}</span></label>
                <button type="submit" class="nx-btn nx-btn-primary"><i class="fas fa-file-import" aria-hidden="true"></i> {{ __('Upload') }}</button>
            </div>
        </x-nit::card>
    </form>
</div>
@endsection
