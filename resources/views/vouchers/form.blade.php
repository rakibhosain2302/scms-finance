@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $voucher->exists ? __('Edit :number', ['number' => $voucher->number]) : __('New :thing', ['thing' => $singular]))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ $voucher->exists ? route("finance.{$route}.update", $voucher->id) : route("finance.{$route}.store") }}" enctype="multipart/form-data" data-nx-once novalidate>
        @csrf
        @if ($voucher->exists) @method('PUT') @endif
        <x-nit::card :title="$voucher->exists ? $voucher->number : __('New :thing', ['thing' => $singular])"
            :subtitle="$kind === 'income' ? __('Dr cash or bank, Cr the income account.') : __('Dr the expense account, Cr cash or bank.')">
            <div class="nx-form-grid">
                @unless ($voucher->exists) @include('finance::partials.branch-picker') @endunless
                <x-nit::input name="voucher_date" type="date" :label="__('Date')" :value="$voucher->voucher_date?->toDateString()" required />
                <x-nit::select name="account_id" :label="$kind === 'income' ? __('Income account') : __('Expense account')" :options="$accounts" :value="$voucher->account_id" required :placeholder="__('Choose…')" />
                <x-nit::select name="cash_bank_account_id" :label="$kind === 'income' ? __('Received into') : __('Paid from')" :options="$cashAccounts" :value="$voucher->cash_bank_account_id" required :placeholder="__('Choose…')" />
                <x-nit::input name="amount" inputmode="decimal" :label="__('Amount')" :value="$voucher->amount" required />
                <x-nit::input :name="$party" :label="$partyLabel" :value="$voucher->{$party}" />
                <x-nit::input name="reference" :label="__('Reference')" :value="$voucher->reference" :hint="__('Bill, receipt or cheque number')" />
                <x-nit::input name="description" :label="__('Narration')" :value="$voucher->description" maxlength="500" wrapper-class="nx-full" />
                <div class="nx-field nx-full">
                    <label for="attachment">{{ __('Attachment') }}</label>
                    <input type="file" id="attachment" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".{{ implode(',.', (array) config('scms-finance.attachments.mimes')) }}">
                    @if ($voucher->attachment_path)<p class="nx-hint"><a href="{{ $voucher->attachmentUrl() }}" target="_blank" rel="noopener">{{ __('Current attachment') }}</a> — {{ __('a new file replaces it.') }}</p>@endif
                    @error('attachment')<p class="nx-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="$voucher->exists ? route('finance.'.$route.'.show', $voucher->id) : route('finance.'.$route.'.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button variant="light" icon="fas fa-save" name="post" value="0">{{ __('Save draft') }}</x-nit::button>
                @if ($canPost)<x-nit::button icon="fas fa-check" name="post" value="1">{{ __('Save and post') }}</x-nit::button>@endif
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
