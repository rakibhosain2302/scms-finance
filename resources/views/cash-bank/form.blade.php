@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $cash->exists ? __('Edit :name', ['name' => $cash->name]) : __('Add cash or bank account'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ $cash->exists ? route('finance.cash-bank.update', $cash) : route('finance.cash-bank.store') }}" data-nx-once novalidate>
        @csrf
        @if ($cash->exists) @method('PUT') @endif
        <x-nit::card :title="$cash->exists ? $cash->name : __('Add cash or bank account')" :subtitle="$cash->exists ? __('The branch, kind and ledger account stay as they were opened.') : __('The opening balance is posted against Opening balance equity.')">
            <div class="nx-form-grid">
                @unless ($cash->exists)
                    @include('finance::partials.branch-picker')
                    <x-nit::select name="kind" :label="__('Kind')" :options="\SCMS\Finance\Enums\CashBankKind::options()" required :placeholder="__('Choose…')" />
                    <div class="nx-field">
                        <label for="account_id">{{ __('Ledger account') }} <span class="nx-req" aria-hidden="true">*</span></label>
                        <select id="account_id" name="account_id" class="form-select @error('account_id') is-invalid @enderror" required>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($glAccounts as $gl)
                                <option value="{{ $gl->id }}" @selected((string) old('account_id') === (string) $gl->id)>{{ $gl->code }} · {{ $gl->name }} ({{ $gl->subtype->label() }})</option>
                            @endforeach
                        </select>
                        @error('account_id')<p class="nx-error">{{ $message }}</p>@enderror
                    </div>
                @endunless
                <x-nit::input name="name" :label="__('Name')" :value="$cash->name" required :placeholder="__('e.g. Main cash, Sonali Bank current')" />
                <x-nit::input name="bank_name" :label="__('Bank')" :value="$cash->bank_name" />
                <x-nit::input name="bank_branch" :label="__('Bank branch')" :value="$cash->bank_branch" />
                <x-nit::input name="account_number" :label="__('Account number')" :value="$cash->account_number" />
                @unless ($cash->exists)
                    <x-nit::input name="opening_balance" inputmode="decimal" :label="__('Opening balance')" value="0" :hint="__('Money in the drawer or account today.')" />
                    <x-nit::input name="opening_date" type="date" :label="__('Opening date')" :value="today()->toDateString()" />
                @endunless
                <div class="nx-field">
                    <input type="hidden" name="is_active" value="0">
                    <label class="nx-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $cash->is_active ? '1' : '0') === '1')> <span>{{ __('Active') }}</span></label>
                </div>
                <x-nit::textarea name="description" :label="__('Note')" :value="$cash->description" rows="2" wrapper-class="nx-full" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="$cash->exists ? route('finance.cash-bank.show', $cash) : route('finance.cash-bank.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Save') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
