@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Chart of accounts'))
@include('finance::partials.styles')

@can(\SCMS\Finance\Support\Abilities::ACCOUNT_MANAGE)
@push('buttons')
<a href="{{ route('finance.accounts.create') }}" class="btn btn-sm btn-nit fw-bold"><i class="fas fa-plus" aria-hidden="true"></i> {{ __('Add account') }}</a>
@endpush
@endcan

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="__('Chart of accounts')" :subtitle="__('Balances of the branches you see, from posted entries. Group accounts add up their sub-accounts.')" :padding="false">
        @if ($accounts->isEmpty())
            <x-nit::empty-state icon="fas fa-book" :title="__('No accounts yet')" :text="__('Run php artisan finance:install to add the default chart.')" />
        @else
        <div class="table-responsive">
            <table class="nx-tbl">
                <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th>{{ __('Use') }}</th><th class="fin-num">{{ __('Balance') }}</th><th><span class="visually-hidden">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                    @php
                        $render = function ($parentKey, $depth) use (&$render, $byParent, $balances) {
                            foreach ($byParent[$parentKey] ?? [] as $account) {
                                echo view('finance::accounts.row', ['account' => $account, 'depth' => $depth, 'balance' => $balances[$account->id]])->render();
                                $render($account->id, $depth + 1);
                            }
                        };
                        $render('', 0);
                    @endphp
                </tbody>
            </table>
        </div>
        @endif
    </x-nit::card>
</div>
@endsection
