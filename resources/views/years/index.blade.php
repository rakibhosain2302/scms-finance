@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Financial years'))

@push('buttons')
<a href="{{ route('finance.financial-years.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('Open a year') }}</a>
@endpush

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::table :rows="$years" :title="__('Financial years')" :subtitle="__('Postings go into the open year that contains their date. A closed year takes none.')"
        :columns="['name' => __('Year'), 'period' => __('Period'), 'status' => __('Status')]" row-actions :empty="__('No financial year yet')" empty-icon="fas fa-calendar">
        @foreach ($years as $year)
            @php($open = ($year->status->value ?? $year->status) === 'open')
            <tr>
                <td><strong>{{ $year->name }}</strong></td>
                <td>{{ \Illuminate\Support\Carbon::parse($year->starts_on)->format('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($year->ends_on)->format('j M Y') }}</td>
                <td>@if ($open)<x-nit::badge tone="success">{{ __('Open') }}</x-nit::badge>@else<x-nit::badge>{{ __('Closed') }}</x-nit::badge> <span class="nx-muted fs-7">{{ $year->closed_at?->format('j M Y') }}</span>@endif</td>
                <td class="text-end">
                    @if ($open)
                        <form method="POST" action="{{ route('finance.financial-years.close', $year->id) }}" class="d-inline"
                            data-nx-confirm="{{ __('Close :name?', ['name' => $year->name]) }}"
                            data-nx-confirm-text="{{ __('Income and expense of the year move to Accumulated surplus, and the year takes no more postings. This cannot be undone.') }}"
                            data-nx-confirm-button="{{ __('Close year') }}">
                            @csrf
                            <button type="submit" class="nx-btn nx-btn-light"><i class="fas fa-lock" aria-hidden="true"></i> {{ __('Close') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
