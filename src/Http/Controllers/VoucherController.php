<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Http\Requests\ReasonRequest;
use SCMS\Finance\Http\Requests\VoucherRequest;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Voucher;
use SCMS\Finance\Services\VoucherService;

/** Income and expense vouchers share these screens; the subclasses name the kind. */
abstract class VoucherController extends Controller
{
    /** @return class-string<Voucher> */
    abstract protected function model(): string;

    abstract protected function service(): VoucherService;

    abstract protected function accountType(): AccountType;

    /** "incomes" / "expenses": route names. */
    abstract protected function routeName(): string;

    protected function ability(string $verb): string
    {
        return 'finance_'.$this->model()::kind().'_'.$verb;
    }

    public function index(Request $request)
    {
        $this->authorizeAbility($this->ability('view'));

        $vouchers = $this->model()::query()->with(['account:id,code,name', 'cashBankAccount:id,name', 'branch:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('account'), fn ($q, $a) => $q->where('account_id', $a))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('voucher_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('voucher_date', '<=', $d))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q->where('number', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('description', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderByDesc('voucher_date')->orderByDesc('id')->paginate($this->perPage())->withQueryString();

        return $this->view('vouchers.index', $this->labels() + [
            'vouchers' => $vouchers,
            'statuses' => EntryStatus::options(),
            'accounts' => Account::options(null, $this->accountType()),
        ]);
    }

    public function create()
    {
        $this->authorizeAbility($this->ability('create'));
        $model = $this->model();

        return $this->view('vouchers.form', $this->formData(new $model(['voucher_date' => today()])));
    }

    public function store(VoucherRequest $request)
    {
        $this->authorizeAbility($this->ability('create'));
        $post = $request->boolean('post') && $request->user()->can($this->ability('post'));

        return $this->attempt(
            fn () => $this->service()->create($request->validated(), $request->file('attachment'), $post, $request->user()),
            fn (Voucher $v) => redirect()->route("finance.{$this->routeName()}.show", $v->id)
                ->with('success', $v->isPosted() ? __(':number posted.', ['number' => $v->number]) : __('Draft :number saved.', ['number' => $v->number])),
        );
    }

    public function show(int $voucher)
    {
        $voucher = $this->find($voucher);
        $this->authorizeAbility('view', $voucher);
        $voucher->load(['account', 'cashBankAccount', 'journalEntry.lines.account', 'reversalEntry', 'creator', 'poster', 'branch']);

        return $this->view('vouchers.show', $this->labels() + ['voucher' => $voucher]);
    }

    public function edit(int $voucher)
    {
        $voucher = $this->find($voucher);
        $this->authorizeAbility('update', $voucher);

        return $this->view('vouchers.form', $this->formData($voucher));
    }

    public function update(VoucherRequest $request, int $voucher)
    {
        $voucher = $this->find($voucher);
        $this->authorizeAbility('update', $voucher);
        $post = $request->boolean('post') && $request->user()->can('post', $voucher);

        return $this->attempt(function () use ($request, $voucher, $post) {
            $voucher = $this->service()->update($voucher, $request->validated(), $request->file('attachment'), $request->user());

            return $post ? $this->service()->post($voucher, $request->user()) : $voucher;
        }, fn (Voucher $v) => redirect()->route("finance.{$this->routeName()}.show", $v->id)->with('success', __(':number saved.', ['number' => $v->number])));
    }

    public function post(Request $request, int $voucher)
    {
        $voucher = $this->find($voucher);
        $this->authorizeAbility('post', $voucher);

        return $this->attempt(
            fn () => $this->service()->post($voucher, $request->user()),
            fn (Voucher $v) => redirect()->route("finance.{$this->routeName()}.show", $v->id)->with('success', __(':number posted.', ['number' => $v->number])),
        );
    }

    public function cancel(Request $request, int $voucher)
    {
        $voucher = $this->find($voucher);
        $this->authorizeAbility('update', $voucher);

        return $this->attempt(
            fn () => $this->service()->cancel($voucher, $request->user()),
            fn (Voucher $v) => redirect()->route("finance.{$this->routeName()}.show", $v->id)->with('success', __('Draft cancelled.')),
        );
    }

    public function reverse(ReasonRequest $request, int $voucher)
    {
        $voucher = $this->find($voucher);
        $this->authorizeAbility('reverse', $voucher);

        return $this->attempt(
            fn () => $this->service()->reverse($voucher, $request->validated('reason'), $request->user()),
            fn (Voucher $v) => redirect()->route("finance.{$this->routeName()}.show", $v->id)->with('success', __(':number reversed.', ['number' => $v->number])),
        );
    }

    /** Within the branch scope: another branch's voucher is "not found". */
    protected function find(int $id): Voucher
    {
        return $this->model()::query()->findOrFail($id);
    }

    abstract protected function labels(): array;

    private function formData(Voucher $voucher): array
    {
        $branch = $this->formBranch($voucher->branch_id);

        return $this->labels() + [
            'voucher' => $voucher,
            'branches' => $this->branchOptions(),
            'branch' => $branch,
            'accounts' => Account::options($branch, $this->accountType()),
            'cashAccounts' => CashBankAccount::options($branch),
            'canPost' => auth()->user()->can($this->ability('post')),
        ];
    }
}
