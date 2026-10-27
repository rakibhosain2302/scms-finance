@extends(config('scms-finance.layout', 'nit::master'))
@section('title', $transfer->exists ? __('Edit :number', ['number' => $transfer->number]) : __('New transfer'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ $transfer->exists ? route('finance.transfers.update', $transfer) : route('finance.transfers.store') }}" data-nx-once novalidate>
        @csrf
        @if ($transfer->exists) @method('PUT') @endif
        <x-nit::card :title="$transfer->exists ? $transfer->number : __('New transfer')" :subtitle="__('Dr the account the money goes to, Cr the account it leaves.')">
            <div class="nx-form-grid">
                @unless ($transfer->exists) @include('finance::partials.branch-picker') @endunless
                <x-nit::input name="transfer_date" type="date" :label="__('Date')" :value="$transfer->transfer_date?->toDateString()" required />
                <x-nit::select name="from_account_id" :label="__('From')" :options="$cashAccounts" :value="$transfer->from_account_id" required :placeholder="__('Choose…')" />
                <x-nit::select name="to_account_id" :label="__('To')" :options="$cashAccounts" :value="$transfer->to_account_id" required :placeholder="__('Choose…')" />
                <x-nit::input name="amount" inputmode="decimal" :label="__('Amount')" :value="$transfer->amount" required />
                <x-nit::input name="reference" :label="__('Reference')" :value="$transfer->reference" :hint="__('Cheque or deposit slip number')" />
                <x-nit::input name="description" :label="__('Narration')" :value="$transfer->description" wrapper-class="nx-full" maxlength="500" />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="$transfer->exists ? route('finance.transfers.show', $transfer) : route('finance.transfers.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button variant="light" icon="fas fa-save" name="post" value="0">{{ __('Save draft') }}</x-nit::button>
                @if ($canPost)<x-nit::button icon="fas fa-check" name="post" value="1">{{ __('Save and post') }}</x-nit::button>@endif
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
