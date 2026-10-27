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
</form>
