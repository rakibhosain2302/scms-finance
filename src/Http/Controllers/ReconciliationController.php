<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use SCMS\Finance\Enums\CashBankKind;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Reconciliation;
use SCMS\Finance\Services\ReconciliationService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;

/** Bank reconciliation: statement → tick the cleared lines → complete when they match. */
class ReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $service)
    {
    }

    public function index()
    {
        $this->authorizeAbility(Abilities::CASHBANK_RECONCILE);

        return $this->view('reconciliations.index', [
            'reconciliations' => Reconciliation::query()->with(['cashBankAccount', 'branch:id,name', 'creator:id,name'])
                ->latest('statement_date')->latest('id')->paginate($this->perPage()),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAbility(Abilities::CASHBANK_RECONCILE);

        return $this->view('reconciliations.create', [
            'accounts' => CashBankAccount::options(null, CashBankKind::Bank),
            'selected' => $request->integer('cash_bank_account_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAbility(Abilities::CASHBANK_RECONCILE);
        $data = $request->validate([
            'cash_bank_account_id' => ['required', 'integer'],
            'statement_date' => ['required', 'date', 'before_or_equal:today'],
            'statement_balance' => ['required', 'decimal:0,'.Money::scale(), 'min:-9999999999999', 'max:9999999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        // The branch scope hides other branches' accounts: 404 instead of someone else's account.
        $account = CashBankAccount::query()->findOrFail($data['cash_bank_account_id']);

        return $this->attempt(
            fn () => $this->service->start($account, $data, $request->user()),
            fn (Reconciliation $r) => redirect()->route('finance.reconciliations.show', $r),
        );
    }

    public function show(Reconciliation $reconciliation)
    {
        $this->authorizeAbility(Abilities::CASHBANK_RECONCILE);
        $reconciliation->load(['cashBankAccount', 'creator', 'completer']);

        return $this->view('reconciliations.show', [
            'reconciliation' => $reconciliation,
            'lines' => $reconciliation->isDraft() ? $this->service->candidates($reconciliation) : $reconciliation->lines()->with('entry')->get(),
            'ticked' => $reconciliation->lines()->pluck('finance_journal_lines.id')->map(fn ($id) => (int) $id)->all(),
            'summary' => $this->service->summary($reconciliation),
        ]);
    }

    public function update(Request $request, Reconciliation $reconciliation)
    {
        $this->authorizeAbility(Abilities::CASHBANK_RECONCILE);
        $ids = $request->validate(['lines' => ['array'], 'lines.*' => ['integer']])['lines'] ?? [];

        return $this->attempt(fn () => $this->service->clear($reconciliation, $ids, $request->user()), function (Reconciliation $r) use ($request) {
            if ($request->boolean('complete')) {
                return $this->attempt(
                    fn () => $this->service->complete($r, $request->user()),
                    fn () => redirect()->route('finance.reconciliations.show', $r)->with('success', __('Reconciliation completed.')),
                );
            }

            return redirect()->route('finance.reconciliations.show', $r)->with('success', __('Saved.'));
        });
    }

    public function destroy(Request $request, Reconciliation $reconciliation)
    {
        $this->authorizeAbility(Abilities::CASHBANK_RECONCILE);

        return $this->attempt(
            fn () => $this->service->delete($reconciliation, $request->user()),
            fn () => redirect()->route('finance.reconciliations.index')->with('success', __('Reconciliation deleted.')),
        );
    }
}
