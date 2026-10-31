@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $type === 'refund' ? __('Refund shares') : __('Sell shares'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ route('finance.shares.store') }}" data-nx-once novalidate>
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <x-nit::card :title="$type === 'refund' ? __('Refund shares') : __('Sell shares')" :subtitle="$price ? __('One share costs :price.', ['price' => \SCMS\Finance\Support\Money::of((string) $price)->format()]) : null">
            <div class="nx-form-grid">
                @include('finance::partials.branch-picker')
                @include('finance::savings.member-picker')
                @if ($balance)<div class="nx-field"><label>{{ __('Shares held') }}</label><p class="fw-semibold mb-0">{{ $balance->format() }}</p></div>@endif
                <x-nit::input name="amount" inputmode="decimal" :label="__('Amount')" required />
                @unless ($price)<x-nit::input name="shares" type="number" min="1" :label="__('Number of shares')" :hint="__('Optional')" />@endunless
                <x-nit::input name="transaction_date" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-nit::select name="cash_bank_account_id" :label="$type === 'refund' ? __('Paid from') : __('Received into')" :options="$cashAccounts" required :placeholder="__('Choose…')" />
                <x-nit::input name="reference" :label="__('Reference')" />
                <x-nit::input name="description" :label="__('Note')" maxlength="500" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.shares.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Post') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
