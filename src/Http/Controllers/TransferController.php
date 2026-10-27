<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Http\Requests\ReasonRequest;
use SCMS\Finance\Http\Requests\TransferRequest;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Transfer;
use SCMS\Finance\Services\TransferService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\MakerChecker;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAbility(Abilities::TRANSFER_VIEW);

        $transfers = Transfer::query()->with(['fromAccount:id,name', 'toAccount:id,name', 'branch:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('transfer_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('transfer_date', '<=', $d))
            ->orderByDesc('transfer_date')->orderByDesc('id')->paginate($this->perPage())->withQueryString();

        return $this->view('transfers.index', ['transfers' => $transfers, 'statuses' => EntryStatus::options()]);
    }

    public function create()
    {
        $this->authorizeAbility(Abilities::TRANSFER_CREATE);

        return $this->view('transfers.form', $this->formData(new Transfer(['transfer_date' => today()])));
    }

    public function store(TransferRequest $request)
    {
        $this->authorizeAbility(Abilities::TRANSFER_CREATE);
        $post = $request->boolean('post') && $request->user()->can(Abilities::TRANSFER_POST) && MakerChecker::selfPostingAllowed($request->user());

        return $this->attempt(
            fn () => $this->transfers->create($request->validated(), $post, $request->user()),
            fn (Transfer $t) => redirect()->route('finance.transfers.show', $t)
                ->with('success', $t->isPosted() ? __(':number posted.', ['number' => $t->number]) : __('Draft :number saved.', ['number' => $t->number])),
        );
    }

    public function show(Transfer $transfer)
    {
        $this->authorizeAbility('view', $transfer);
        $transfer->load(['fromAccount', 'toAccount', 'journalEntry.lines.account', 'reversalEntry', 'creator', 'poster', 'branch']);

        return $this->view('transfers.show', ['transfer' => $transfer]);
    }

    public function edit(Transfer $transfer)
    {
        $this->authorizeAbility('update', $transfer);

        return $this->view('transfers.form', $this->formData($transfer));
    }

    public function update(TransferRequest $request, Transfer $transfer)
    {
        $this->authorizeAbility('update', $transfer);
        $post = $request->boolean('post') && $request->user()->can('post', $transfer);

        return $this->attempt(function () use ($request, $transfer, $post) {
            $transfer = $this->transfers->update($transfer, $request->validated(), $request->user());

            return $post ? $this->transfers->post($transfer, $request->user()) : $transfer;
        }, fn (Transfer $t) => redirect()->route('finance.transfers.show', $t)->with('success', __(':number saved.', ['number' => $t->number])));
    }

    public function post(Request $request, Transfer $transfer)
    {
        $this->authorizeAbility('post', $transfer);

        return $this->attempt(fn () => $this->transfers->post($transfer, $request->user()),
            fn (Transfer $t) => redirect()->route('finance.transfers.show', $t)->with('success', __(':number posted.', ['number' => $t->number])));
    }

    public function cancel(Request $request, Transfer $transfer)
    {
        $this->authorizeAbility('update', $transfer);

        return $this->attempt(fn () => $this->transfers->cancel($transfer, $request->user()),
            fn (Transfer $t) => redirect()->route('finance.transfers.show', $t)->with('success', __('Draft cancelled.')));
    }

    public function reverse(ReasonRequest $request, Transfer $transfer)
    {
        $this->authorizeAbility('reverse', $transfer);

        return $this->attempt(fn () => $this->transfers->reverse($transfer, $request->validated('reason'), $request->user()),
            fn (Transfer $t) => redirect()->route('finance.transfers.show', $t)->with('success', __(':number reversed.', ['number' => $t->number])));
    }

    private function formData(Transfer $transfer): array
    {
        $branch = $this->formBranch($transfer->branch_id);

        return [
            'transfer' => $transfer,
            'branches' => $this->branchOptions(),
            'branch' => $branch,
            'cashAccounts' => CashBankAccount::options($branch),
            'canPost' => auth()->user()->can(Abilities::TRANSFER_POST) && MakerChecker::selfPostingAllowed(auth()->user()),
        ];
    }
}
