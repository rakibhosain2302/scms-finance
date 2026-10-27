@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $scheme->exists ? __('Edit :name', ['name' => $scheme->name]) : __('Add savings scheme'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    <form method="POST" action="{{ $scheme->exists ? route('finance.savings-schemes.update', $scheme) : route('finance.savings-schemes.store') }}" data-nx-once novalidate>
        @csrf
        @if ($scheme->exists) @method('PUT') @endif
        <x-nit::card :title="$scheme->exists ? $scheme->name : __('Add savings scheme')">
            <div class="nx-form-grid">
                <x-nit::input name="code" :label="__('Code')" :value="$scheme->code" required maxlength="20" />
                <x-nit::input name="name" :label="__('Name')" :value="$scheme->name" required :placeholder="__('e.g. Monthly Savings')" />
                <x-nit::select name="frequency" :label="__('Frequency')" :options="$frequencies" :value="$scheme->frequency?->value" required />
                <x-nit::select name="liability_account_id" :label="__('Liability account')" :options="$liabilityAccounts" :value="$scheme->liability_account_id" required :placeholder="__('Choose…')" :hint="__('Member savings account in the chart.')" />
                <x-nit::input name="min_deposit" inputmode="decimal" :label="__('Smallest deposit')" :value="$scheme->min_deposit" required />
                <x-nit::input name="max_deposit" inputmode="decimal" :label="__('Largest deposit')" :value="$scheme->max_deposit" :hint="__('Empty = no limit')" />
                <x-nit::input name="minimum_balance" inputmode="decimal" :label="__('Minimum balance')" :value="$scheme->minimum_balance" required :hint="__('Must stay after a withdrawal.')" />
                <x-nit::input name="max_withdrawals_per_month" type="number" min="1" :label="__('Withdrawals a month')" :value="$scheme->max_withdrawals_per_month" :hint="__('Empty = no limit')" />
                <x-nit::input name="interest_rate" inputmode="decimal" :label="__('Interest rate % a year')" :value="$scheme->interest_rate" :hint="__('For information; interest is posted by journal.')" />
                <div class="nx-field">
                    <input type="hidden" name="allow_withdrawal" value="0">
                    <label class="nx-check"><input type="checkbox" name="allow_withdrawal" value="1" @checked(old('allow_withdrawal', $scheme->allow_withdrawal ? '1' : '0') === '1')> <span>{{ __('Withdrawals allowed') }}</span></label>
                    <input type="hidden" name="is_active" value="0">
                    <label class="nx-check mt-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $scheme->is_active ? '1' : '0') === '1')> <span>{{ __('Active') }} <span class="nx-hint d-block mt-0">{{ __('Turned off schemes take no new accounts.') }}</span></span></label>
                </div>
                <x-nit::textarea name="description" :label="__('Description')" :value="$scheme->description" rows="2" wrapper-class="nx-full" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.savings-schemes.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Save') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
