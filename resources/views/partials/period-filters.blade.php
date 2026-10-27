{{-- Branch and period filters of the reports. Variables from ReportController::filters(); a section "report-filters" adds fields. --}}
<form method="get" class="nx-filters fin-no-print" role="search">
    @if (count($branchOptions) > 1)
        <select name="branch_id" class="form-select nx-filter-select" aria-label="{{ __('Branch') }}">
            <option value="">{{ __('All my branches') }}</option>
            @foreach ($branchOptions as $id => $name)
                <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
            @endforeach
        </select>
    @endif
    @if ($from ?? null)
        <label class="d-flex align-items-center gap-2">{{ __('From') }} <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></label>
        <label class="d-flex align-items-center gap-2">{{ __('To') }} <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></label>
    @else
        <label class="d-flex align-items-center gap-2">{{ __('As of') }} <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></label>
    @endif
    @yield('report-filters')
    <button type="submit" class="nx-btn nx-btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> {{ __('Show') }}</button>
    <button type="button" class="nx-btn nx-btn-light" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i> {{ __('Print') }}</button>
    <button type="button" class="nx-btn nx-btn-light" data-fin-csv="{{ \Illuminate\Support\Str::slug(trim($__env->yieldContent('title')) ?: 'report') }}-{{ $to->toDateString() }}.csv"><i class="fas fa-file-csv" aria-hidden="true"></i> {{ __('CSV') }}</button>
</form>

@once
@push('js')
<script>
    // CSV of the report's tables as shown (Excel opens it as UTF-8 thanks to the BOM; Bangla stays readable).
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-fin-csv]');
        if (!button) return;
        var cell = function (td) {
            var text = td.innerText.replace(/\s+/g, ' ').trim();
            if (/^[=+\-@]/.test(text) && !/^-?[\d,.]+$/.test(text)) text = "'" + text; // no formulas
            return '"' + text.replace(/"/g, '""') + '"';
        };
        var rows = [];
        document.querySelectorAll('.nx-page table').forEach(function (table) {
            if (table.closest('.fin-no-print')) return;
            if (rows.length) rows.push('');
            table.querySelectorAll('tr').forEach(function (tr) {
                rows.push(Array.prototype.map.call(tr.querySelectorAll('th, td'), cell).join(','));
            });
        });
        var blob = new Blob(['\ufeff' + rows.join('\r\n')], {type: 'text/csv;charset=utf-8'});
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = button.getAttribute('data-fin-csv');
        document.body.appendChild(link);
        link.click();
        link.remove();
    });
</script>
@endpush
@endonce
