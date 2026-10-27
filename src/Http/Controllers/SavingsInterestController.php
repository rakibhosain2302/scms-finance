<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\SavingsInterestRun;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Services\SavingsInterestService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;

/** Interest on savings: pick a scheme, branch and period, check the preview, post. */
class SavingsInterestController extends Controller
{
    public function __construct(private readonly SavingsInterestService $interest)
    {
    }

    public function create(Request $request)
    {
        $this->authorizeAbility(Abilities::SAVINGS_INTEREST);
        $schemes = SavingsScheme::query()->where('interest_rate', '>', 0)->orderBy('name')->get();
        $filters = $request->validate([
            'savings_scheme_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
        ]);

        $scheme = $schemes->firstWhere('id', (int) ($filters['savings_scheme_id'] ?? 0));
        $branch = $this->formBranch($filters['branch_id'] ?? null);
        $from = isset($filters['period_from']) ? Carbon::parse($filters['period_from']) : today()->subMonthNoOverflow()->startOfMonth();
        $to = isset($filters['period_to']) ? Carbon::parse($filters['period_to']) : today()->subMonthNoOverflow()->endOfMonth();

        $preview = null;
        $error = null;
        if ($scheme && $branch && array_key_exists($branch, $this->branchOptions())) {
            try {
                $preview = $this->interest->preview($scheme, $branch, $from, $to);
            } catch (FinanceException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->view('savings.interest', [
            'schemes' => $schemes,
            'scheme' => $scheme,
            'branches' => $this->branchOptions(),
            'branch' => $branch,
            'from' => $from,
            'to' => $to,
            'preview' => $preview,
            'previewTotal' => $preview ? Money::sum($preview->pluck('interest')->all()) : null,
            'error' => $error,
            'members' => $preview ? $this->members()->labels($preview->pluck('account.member_id')) : [],
            'runs' => SavingsInterestRun::query()->with(['scheme:id,name', 'branch:id,name', 'creator:id,name'])->latest('id')->limit(20)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAbility(Abilities::SAVINGS_INTEREST);
        $data = $request->validate([
            'savings_scheme_id' => ['required', 'integer', 'exists:finance_savings_schemes,id'],
            'branch_id' => ['required', 'integer', Rule::in(array_keys($this->branchOptions()))],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'posting_date' => ['required', 'date', 'after_or_equal:period_to', 'before_or_equal:today'],
        ]);

        return $this->attempt(
            fn () => $this->interest->post(SavingsScheme::query()->findOrFail($data['savings_scheme_id']), (int) $data['branch_id'],
                Carbon::parse($data['period_from']), Carbon::parse($data['period_to']), Carbon::parse($data['posting_date']), $request->user()),
            fn (SavingsInterestRun $run) => redirect()->route('finance.savings-interest.create')
                ->with('success', __('Interest of :total posted on :n accounts.', ['total' => Money::of($run->total)->format(), 'n' => $run->accounts_count])),
        );
    }
}
