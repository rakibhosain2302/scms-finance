<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SCMS\Finance\Enums\SavingsAccountStatus;
use SCMS\Finance\Http\Requests\SavingsAccountRequest;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Services\SavingsService;
use SCMS\Finance\Support\Abilities;

class SavingsAccountController extends Controller
{
    public function __construct(private readonly SavingsService $savings)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAbility(Abilities::SAVINGS_VIEW);

        $accounts = SavingsAccount::query()->with(['scheme:id,name', 'branch:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('scheme'), fn ($q, $s) => $q->where('savings_scheme_id', $s))
            ->when($request->query('member'), fn ($q, $m) => $q->where('member_id', $m))
            ->when($request->query('q'), fn ($q, $term) => $q->where('account_number', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderByDesc('id')->paginate($this->perPage())->withQueryString();

        return $this->view('savings.accounts.index', [
            'accounts' => $accounts,
            'members' => $this->members()->labels($accounts->pluck('member_id')),
            'schemes' => SavingsScheme::query()->orderBy('name')->pluck('name', 'id')->all(),
            'statuses' => SavingsAccountStatus::options(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAbility(Abilities::SAVINGS_MANAGE);
        $branch = $this->formBranch();

        return $this->view('savings.accounts.create', [
            'branches' => $this->branchOptions(),
            'branch' => $branch,
            'memberSearch' => $request->query('member_q'),
            'members' => $this->members()->options($branch ? [$branch] : $this->visibleBranches(), $request->query('member_q')),
            'membersAvailable' => $this->members()->available(),
            'schemes' => SavingsScheme::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(SavingsAccountRequest $request)
    {
        $this->authorizeAbility(Abilities::SAVINGS_MANAGE);

        return $this->attempt(
            fn () => $this->savings->open($request->validated(), $request->user()),
            fn (SavingsAccount $a) => redirect()->route('finance.savings-accounts.show', $a)->with('success', __('Account :number opened.', ['number' => $a->account_number])),
        );
    }

    public function show(SavingsAccount $savingsAccount, LedgerService $ledger)
    {
        $this->authorizeAbility('view', $savingsAccount);

        return $this->view('savings.accounts.show', [
            'account' => $savingsAccount->load(['scheme', 'branch', 'creator']),
            'member' => $savingsAccount->memberLabel(),
            'balance' => $ledger->savingsBalance($savingsAccount->id),
            'transactions' => $savingsAccount->transactions()->with('cashBankAccount:id,name')->orderByDesc('transaction_date')->orderByDesc('id')->paginate($this->perPage()),
            'statuses' => SavingsAccountStatus::options(),
        ]);
    }

    public function status(Request $request, SavingsAccount $savingsAccount)
    {
        $this->authorizeAbility('update', $savingsAccount);
        $data = $request->validate(['status' => ['required', Rule::enum(SavingsAccountStatus::class)]]);

        return $this->attempt(
            fn () => $this->savings->setStatus($savingsAccount, SavingsAccountStatus::from($data['status']), $request->user()),
            fn (SavingsAccount $a) => redirect()->route('finance.savings-accounts.show', $a)->with('success', __('The account is now :status.', ['status' => $a->status->label()])),
        );
    }
}
