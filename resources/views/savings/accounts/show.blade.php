@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Savings account :number', ['number' => $account->account_number]))
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <x-nit::card :title="$account->account_number" :subtitle="$member.' · '.$account->scheme->name">
        <x-slot:actions>@include('finance::partials.status', ['status' => $account->status])</x-slot:actions>
        <div class="fin-tiles mb-5">
            <div class="fin-tile"><small>{{ __('Balance (ledger)') }}</small><strong>{{ $balance->format() }}</strong></div>
            <div class="fin-tile"><small>{{ __('Minimum balance') }}</small><strong>@finmoney($account->scheme->minimum_balance)</strong></div>
            <div class="fin-tile"><small>{{ __('Can be withdrawn') }}</small><strong>{{ $account->scheme->allow_withdrawal ? \SCMS\Finance\Support\Money::max($balance->minus($account->scheme->minimum_balance), 0)->format() : __('No withdrawals') }}</strong></div>
        </div>
        @unless ($balance->equals($account->balance))
            <div class="nx-alert mb-5" role="alert"><i class="fas fa-exclamation-triangle mt-1" aria-hidden="true"></i> <span>{{ __('The cached balance (:cached) differs from the ledger. Run php artisan finance:rebuild-balances --fix.', ['cached' => \SCMS\Finance\Support\Money::of($account->balance)->format()]) }}</span></div>
        @endunless
        <dl class="fin-facts">
            <dt>{{ __('Branch') }}</dt><dd>{{ $account->branch?->name }}</dd>
            <dt>{{ __('Opened') }}</dt><dd>{{ $account->opened_on->format('j M Y') }}</dd>
            @if ($account->maturity_date)<dt>{{ __('Matures') }}</dt><dd>{{ $account->maturity_date->format('j M Y') }}</dd>@endif
            @if ($account->installment_amount)<dt>{{ __('Regular deposit') }}</dt><dd>@finmoney($account->installment_amount)</dd>@endif
            @if ($account->closed_on)<dt>{{ __('Closed') }}</dt><dd>{{ $account->closed_on->format('j M Y') }}</dd>@endif
            <dt>{{ __('Opened by') }}</dt><dd>{{ $account->creator?->name ?? '—' }}</dd>
            @if ($account->note)<dt>{{ __('Note') }}</dt><dd>{{ $account->note }}</dd>@endif
        </dl>
        <x-slot:footer>
            <x-nit::button variant="light" :href="route('finance.savings-accounts.index')" icon="fas fa-arrow-left">{{ __('Savings accounts') }}</x-nit::button>
            @can('update', $account)
                @if ($account->status !== \SCMS\Finance\Enums\SavingsAccountStatus::Closed)
                    <form method="POST" action="{{ route('finance.savings-accounts.status', $account) }}" class="d-inline-flex gap-2" data-nx-confirm="{{ __('Change the account status?') }}" data-nx-confirm-button="{{ __('Change') }}">
                        @csrf
                        <select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                            @foreach ($statuses as $v => $l)@if ($v !== $account->status->value)<option value="{{ $v }}">{{ $l }}</option>@endif @endforeach
                        </select>
                        <button type="submit" class="nx-btn nx-btn-light">{{ __('Set') }}</button>
                    </form>
                @endif
            @endcan
            @can('withdraw', $account)<x-nit::button variant="light" :href="route('finance.savings-transactions.create', ['type' => 'withdrawal', 'account' => $account->id])" icon="fas fa-minus">{{ __('Withdrawal') }}</x-nit::button>@endcan
            @can('deposit', $account)<x-nit::button :href="route('finance.savings-transactions.create', ['type' => 'deposit', 'account' => $account->id])" icon="fas fa-plus">{{ __('Deposit') }}</x-nit::button>@endcan
        </x-slot:footer>
    </x-nit::card>

    <x-nit::table :rows="$transactions" :title="__('Transactions')" class="mt-5"
        :columns="['number' => __('Number'), 'date' => __('Date'), 'type' => __('Type'), 'via' => __('Cash / bank'), 'in' => ['label' => __('Deposit'), 'class' => 'fin-num'], 'out' => ['label' => __('Withdrawal'), 'class' => 'fin-num'], 'status' => __('Status')]"
        :empty="__('No transactions yet')" empty-icon="fas fa-receipt">
        @foreach ($transactions as $t)
            <tr @class(['text-decoration-line-through nx-muted' => $t->status === \SCMS\Finance\Enums\EntryStatus::Reversed])>
                <td><a href="{{ route('finance.savings-transactions.show', $t) }}">{{ $t->number }}</a></td>
                <td>{{ $t->transaction_date->format('j M Y') }}</td>
                <td>{{ $t->type->label() }}</td>
                <td class="nx-muted">{{ $t->cashBankAccount?->name }}</td>
                <td class="fin-num">{{ $t->type === \SCMS\Finance\Enums\SavingsTransactionType::Deposit ? $t->money('amount')->format() : '' }}</td>
                <td class="fin-num">{{ $t->type === \SCMS\Finance\Enums\SavingsTransactionType::Withdrawal ? $t->money('amount')->format() : '' }}</td>
                <td>@include('finance::partials.status', ['status' => $t->status])</td>
            </tr>
        @endforeach
    </x-nit::table>
</div>
@endsection
