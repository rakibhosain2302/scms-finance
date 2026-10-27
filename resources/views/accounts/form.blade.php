@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $account->exists ? __('Edit account') : __('Add account'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ $account->exists ? route('finance.accounts.update', $account) : route('finance.accounts.store') }}" data-nx-once novalidate>
        @csrf
        @if ($account->exists) @method('PUT') @endif
        <x-nit::card :title="$account->exists ? __('Edit :code', ['code' => $account->code]) : __('Add account')"
            :subtitle="$used ? __('This account has postings: its type, branch and group setting are fixed.') : null">
            <div class="nx-form-grid">
                <x-nit::input name="code" :label="__('Code')" :value="$account->code" required maxlength="20" :readonly="$account->is_system" :hint="$account->is_system ? __('System account: the package finds it by this code.') : __('Unique, e.g. 5110')" />
                <x-nit::input name="name" :label="__('Name')" :value="$account->name" required />
                <x-nit::select name="type" :label="__('Type')" :options="$types" :value="$account->type?->value" required :placeholder="__('Choose…')" />
                <x-nit::select name="subtype" :label="__('Used for')" :options="$subtypes" :value="$account->subtype?->value" required :hint="__('Cash and bank accounts need Cash / Bank; member accounts are used by the savings and loan screens.')" />
                <x-nit::select name="parent_id" :label="__('Under')" :options="$parents" :value="$account->parent_id" :placeholder="__('— Top level —')" :hint="__('A sub-account has the type of its group.')" />
                <x-nit::select name="branch_id" :label="__('Branch')" :options="$branches" :value="$account->branch_id" :placeholder="__('All branches')" />
                <div class="nx-field">
                    <input type="hidden" name="is_postable" value="0">
                    <label class="nx-check"><input type="checkbox" name="is_postable" value="1" @checked(old('is_postable', $account->is_postable ? '1' : '0') === '1') @disabled($used)> <span>{{ __('Takes postings') }} <span class="nx-hint d-block mt-0">{{ __('Off for a group account that only adds up its sub-accounts.') }}</span></span></label>
                    @if ($used)<input type="hidden" name="is_postable" value="1">@endif
                </div>
                <div class="nx-field">
                    <input type="hidden" name="is_active" value="0">
                    <label class="nx-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active ? '1' : '0') === '1')> <span>{{ __('Active') }}</span></label>
                </div>
                <x-nit::textarea name="description" :label="__('Description')" :value="$account->description" rows="2" wrapper-class="nx-full" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.accounts.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Save') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
    @if ($account->exists && ! $account->is_system && ! $used)
        <div class="mt-4 text-end">
            <x-nit::delete-button :action="route('finance.accounts.destroy', $account)" :name="$account->name" :label="__('Delete account')" :text="__('Only an account that was never used can be deleted.')" />
        </div>
    @endif
</div>
@endsection
