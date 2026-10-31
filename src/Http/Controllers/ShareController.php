<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SCMS\Finance\Http\Requests\ReasonRequest;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\ShareTransaction;
use SCMS\Finance\Services\ShareService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;

/** Share capital: members buy shares and get share money back. */
class ShareController extends Controller
{
    public function __construct(private readonly ShareService $shares)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAbility(Abilities::SHARE_VIEW);
        $transactions = ShareTransaction::query()->with(['cashBankAccount:id,name', 'branch:id,name'])
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->integer('member_id'), fn ($q, $id) => $q->where('member_id', $id))
            ->orderByDesc('transaction_date')->orderByDesc('id')->paginate($this->perPage())->withQueryString();

        return $this->view('shares.index', [
            'transactions' => $transactions,
            'members' => $this->members()->labels($transactions->pluck('member_id')),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAbility(Abilities::SHARE_MANAGE);
        $branch = $this->formBranch();
        $memberId = $request->integer('member_id') ?: null;

        return $this->view('shares.create', [
            'type' => $request->query('type') === ShareTransaction::REFUND ? ShareTransaction::REFUND : ShareTransaction::PURCHASE,
            'branches' => $this->branchOptions(),
            'branch' => $branch,
            'memberSearch' => $request->query('member_q'),
            'members' => $this->members()->options($branch ? [$branch] : $this->visibleBranches(), $request->query('member_q')),
            'membersAvailable' => $this->members()->available(),
            'balance' => $memberId ? $this->shares->balance($memberId) : null,
            'cashAccounts' => CashBankAccount::options($branch),
            'price' => config('scms-finance.share_price'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAbility(Abilities::SHARE_MANAGE);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in(array_keys($this->branchOptions()))],
            'member_id' => ['required', 'integer'],
            'type' => ['required', Rule::in([ShareTransaction::PURCHASE, ShareTransaction::REFUND])],
            'amount' => ['required', 'decimal:0,'.Money::scale(), 'gt:0', 'max:9999999999999'],
            'shares' => ['nullable', 'integer', 'min:1'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'cash_bank_account_id' => ['required', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->attempt(
            fn () => $this->shares->post($data, $request->user()),
            fn (ShareTransaction $t) => redirect()->route('finance.shares.show', $t)->with('success', __(':type :number posted.', ['type' => $t->typeLabel(), 'number' => $t->number])),
        );
    }

    public function show(ShareTransaction $share)
    {
        $this->authorizeAbility(Abilities::SHARE_VIEW);
        $share->load(['cashBankAccount', 'journalEntry.lines.account', 'reversalEntry', 'creator', 'branch']);

        return $this->view('shares.show', ['transaction' => $share, 'member' => $share->memberLabel(), 'balance' => $this->shares->balance((int) $share->member_id)]);
    }

    public function reverse(ReasonRequest $request, ShareTransaction $share)
    {
        $this->authorizeAbility(Abilities::REVERSE);

        return $this->attempt(
            fn () => $this->shares->reverse($share, $request->validated('reason'), $request->user()),
            fn (ShareTransaction $t) => redirect()->route('finance.shares.show', $t)->with('success', __(':number reversed.', ['number' => $t->number])),
        );
    }
}
