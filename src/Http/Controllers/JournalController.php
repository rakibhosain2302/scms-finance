<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Http\Requests\JournalRequest;
use SCMS\Finance\Http\Requests\ReasonRequest;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\JournalEntry;
use SCMS\Finance\Services\JournalService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\MakerChecker;

class JournalController extends Controller
{
    public function __construct(private readonly JournalService $journal)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAbility(Abilities::JOURNAL_VIEW);

        $entries = JournalEntry::query()
            ->with('branch:id,name')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('source'), fn ($q, $s) => $q->where('source_type', $s))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('entry_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('entry_date', '<=', $d))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q->where('number', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('description', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate($this->perPage())->withQueryString();

        $sources = JournalEntry::query()->distinct()->orderBy('source_type')->pluck('source_type', 'source_type')
            ->map(fn ($s) => __(ucfirst(str_replace('_', ' ', $s))))->all();

        return $this->view('journals.index', ['entries' => $entries, 'statuses' => EntryStatus::options(), 'sources' => $sources]);
    }

    public function create()
    {
        $this->authorizeAbility(Abilities::JOURNAL_CREATE);

        return $this->view('journals.form', $this->formData(new JournalEntry(['entry_date' => today()])));
    }

    public function store(JournalRequest $request)
    {
        $this->authorizeAbility(Abilities::JOURNAL_CREATE);
        $data = $request->validated();

        return $this->attempt(function () use ($data, $request) {
            $entry = $this->journal->createDraft($data, $data['lines'], $request->user());
            if (! empty($data['post']) && $request->user()->can('post', $entry)) {
                $entry = $this->journal->post($entry, $request->user());
            }

            return $entry;
        }, fn (JournalEntry $e) => redirect()->route('finance.journals.show', $e)
            ->with('success', $e->isPosted() ? __('Entry :number posted.', ['number' => $e->number]) : __('Draft :number saved.', ['number' => $e->number])));
    }

    public function show(JournalEntry $journalEntry)
    {
        $this->authorizeAbility('view', $journalEntry);
        $journalEntry->load(['lines.account', 'lines.cashBankAccount', 'branch', 'financialYear', 'creator', 'poster', 'reversalOf', 'reversedBy']);

        return $this->view('journals.show', ['entry' => $journalEntry]);
    }

    public function edit(JournalEntry $journalEntry)
    {
        $this->authorizeAbility('update', $journalEntry);
        $journalEntry->load('lines');

        return $this->view('journals.form', $this->formData($journalEntry));
    }

    public function update(JournalRequest $request, JournalEntry $journalEntry)
    {
        $this->authorizeAbility('update', $journalEntry);
        $data = $request->validated();

        return $this->attempt(function () use ($data, $request, $journalEntry) {
            $entry = $this->journal->updateDraft($journalEntry, $data, $data['lines'], $request->user());
            if (! empty($data['post']) && $request->user()->can('post', $entry)) {
                $entry = $this->journal->post($entry, $request->user());
            }

            return $entry;
        }, fn (JournalEntry $e) => redirect()->route('finance.journals.show', $e)->with('success', __('Entry :number saved.', ['number' => $e->number])));
    }

    public function post(Request $request, JournalEntry $journalEntry)
    {
        $this->authorizeAbility('post', $journalEntry);

        return $this->attempt(
            fn () => $this->journal->post($journalEntry, $request->user()),
            fn (JournalEntry $e) => redirect()->route('finance.journals.show', $e)->with('success', __('Entry :number posted.', ['number' => $e->number])),
        );
    }

    public function cancel(Request $request, JournalEntry $journalEntry)
    {
        $this->authorizeAbility('update', $journalEntry);

        return $this->attempt(
            fn () => $this->journal->cancelDraft($journalEntry, $request->user()),
            fn (JournalEntry $e) => redirect()->route('finance.journals.show', $e)->with('success', __('Draft cancelled.')),
        );
    }

    public function reverse(ReasonRequest $request, JournalEntry $journalEntry)
    {
        $this->authorizeAbility('reverse', $journalEntry);

        return $this->attempt(
            fn () => $this->journal->reverseManual($journalEntry, $request->validated('reason'), $request->user()),
            fn (JournalEntry $reversal) => redirect()->route('finance.journals.show', $reversal)->with('success', __('Reversed by :number.', ['number' => $reversal->number])),
        );
    }

    private function formData(JournalEntry $entry): array
    {
        $branch = $this->formBranch($entry->branch_id);

        return [
            'entry' => $entry,
            'branches' => $this->branchOptions(),
            'branch' => $branch,
            // Member ledgers move only through the savings and loan screens.
            'accounts' => Account::query()->postable()->whereNotIn('subtype', [AccountSubtype::MemberSavings->value, AccountSubtype::LoanReceivable->value])
                ->orderBy('code')->get(['id', 'code', 'name', 'subtype', 'branch_id']),
            'cashAccounts' => CashBankAccount::query()->active()->with('branch:id,name')->orderBy('name')->get(),
            'canPost' => auth()->user()->can(Abilities::JOURNAL_POST) && MakerChecker::selfPostingAllowed(auth()->user()),
        ];
    }
}
