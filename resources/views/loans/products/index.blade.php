@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Loan products'))
@include('finance::partials.styles')

@push('buttons')
<a href="{{ route('finance.loan-products.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('Add product') }}</a>
@endpush

@section('content')
<div class="nx-page">
    <x-nit::table :rows="$products" :title="__('Loan products')" :subtitle="__('A loan copies its product\'s terms when applied for: later changes affect new loans only.')" row-actions
        :columns="['name' => __('Product'), 'interest' => __('Interest'), 'repayment' => __('Repayment'), 'amount' => ['label' => __('Amount'), 'class' => 'fin-num'], 'loans' => __('Loans'), 'status' => __('Status')]"
        :empty="__('No loan products yet')" empty-icon="fas fa-hand-holding-usd">
        @foreach ($products as $p)
            <tr>
                <td><strong>{{ $p->name }}</strong><span class="nx-muted fs-7 d-block">{{ $p->code }}</span></td>
                <td>{{ $p->interest_method->label() }}@if ($p->interest_method !== \SCMS\Finance\Enums\InterestMethod::None) · {{ rtrim(rtrim($p->interest_rate, '0'), '.') }}% {{ __('a year') }}@endif
                    @if ((string) $p->processing_fee_rate !== '0.0000')<span class="nx-muted fs-7 d-block">{{ __('Fee :rate%', ['rate' => rtrim(rtrim($p->processing_fee_rate, '0'), '.')]) }}</span>@endif</td>
                <td>{{ $p->repayment_frequency->label() }} · {{ $p->min_installments }}–{{ $p->max_installments }}</td>
                <td class="fin-num">@finmoney($p->min_amount) – {{ $p->max_amount !== null ? \SCMS\Finance\Support\Money::of($p->max_amount)->format() : '∞' }}</td>
                <td>{{ $p->loans_count }}</td>
                <td>@if ($p->is_active)<x-nit::badge tone="success">{{ __('Active') }}</x-nit::badge>@else<x-nit::badge>{{ __('Off') }}</x-nit::badge>@endif</td>
                <td class="text-end"><a href="{{ route('finance.loan-products.edit', $p) }}" class="nx-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit :name', ['name' => $p->name]) }}"><i class="fas fa-pen" aria-hidden="true"></i></a></td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
