@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Open savings account'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ route('finance.savings-accounts.store') }}" data-nx-once novalidate>
        @csrf
        <x-nit::card :title="__('Open savings account')" :subtitle="__('Deposits are taken from the account page once it is open.')">
            <div class="nx-form-grid">
                @include('finance::partials.branch-picker')
                @include('finance::savings.member-picker')
                <x-nit::select name="savings_scheme_id" :label="__('Scheme')" :options="$schemes" required :placeholder="__('Choose…')" />
                <x-nit::input name="opened_on" type="date" :label="__('Opened on')" :value="today()->toDateString()" required />
                <x-nit::input name="installment_amount" inputmode="decimal" :label="__('Regular deposit')" :hint="__('Expected each period for daily, weekly or monthly schemes (empty = the scheme\'s smallest deposit).')" />
                @can(\SCMS\Finance\Support\Abilities::SAVINGS_OPENING)
                    <x-nit::input name="opening_balance" inputmode="decimal" :label="__('Opening balance (go-live)')" :hint="__('Only for an account the member already had before the system: credited on the opening date against opening balance equity. No cash moves.')" />
                @endcan
                <x-nit::textarea name="note" :label="__('Note')" rows="2" wrapper-class="nx-full" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.savings-accounts.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Open account') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
