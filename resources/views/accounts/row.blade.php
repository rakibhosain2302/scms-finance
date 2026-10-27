<tr>
    <td class="fin-tree-{{ min($depth, 4) }}">
        @if ($account->is_postable)
            <a href="{{ route('finance.reports.ledger', ['account_id' => $account->id]) }}">{{ $account->code }} · {{ $account->name }}</a>
        @else
            <strong>{{ $account->code }} · {{ $account->name }}</strong>
        @endif
        @if (! $account->is_active)<x-nit::badge>{{ __('Off') }}</x-nit::badge>@endif
        @if ($account->branch_id)<span class="nx-muted fs-7 d-block">{{ __('One branch only') }}</span>@endif
    </td>
    <td class="nx-muted">{{ $account->type->label() }}</td>
    <td class="nx-muted">{{ $account->is_postable ? $account->subtype->label() : __('Group') }}@if ($account->is_system) <i class="fas fa-lock fs-8" title="{{ __('System account') }}"></i>@endif</td>
    <td @class(['fin-num', 'fw-bold' => ! $account->is_postable, 'fin-neg' => $balance->isNegative()])>{{ $balance->format() }}</td>
    <td class="text-end">
        @can(\SCMS\Finance\Support\Abilities::ACCOUNT_MANAGE)
            <div class="nx-actions">
                @unless ($account->is_postable)
                    <a href="{{ route('finance.accounts.create', ['parent' => $account->id]) }}" class="nx-icon-btn" title="{{ __('Add sub-account') }}" aria-label="{{ __('Add a sub-account to :name', ['name' => $account->name]) }}"><i class="fas fa-plus" aria-hidden="true"></i></a>
                @endunless
                <a href="{{ route('finance.accounts.edit', $account) }}" class="nx-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit :name', ['name' => $account->name]) }}"><i class="fas fa-pen" aria-hidden="true"></i></a>
            </div>
        @endcan
    </td>
</tr>
