<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\DividendRun;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Services\DividendService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\FinanceModels;
use SCMS\Finance\Support\Money;

/** Dividend on shares for a closed year: preview, then post. */
class DividendController extends Controller
{
    public function __construct(private readonly DividendService $dividends)
    {
    }

    public function create(Request $request)
    {
        $this->authorizeAbility(Abilities::DIVIDEND_POST);
        $years = FinanceModels::financialYear()::query()->where('status', YearStatus::Closed->value)->orderByDesc('starts_on')->get();
        $year = $years->firstWhere('id', $request->integer('financial_year_id'));
        $scheme = SavingsScheme::query()->find($request->integer('savings_scheme_id'));
        $rate = (string) $request->query('rate', '');

        $preview = null;
        $error = null;
        if ($year && $scheme && is_numeric($rate) && (float) $rate > 0) {
            try {
                $preview = $this->dividends->preview($year, $rate, $scheme);
            } catch (FinanceException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->view('dividends.create', [
            'years' => $years->pluck('name', 'id')->all(),
            'schemes' => SavingsScheme::query()->orderBy('name')->pluck('name', 'id')->all(),
            'year' => $year, 'scheme' => $scheme, 'rate' => $rate, 'preview' => $preview, 'error' => $error,
            'members' => $preview ? $this->members()->labels(array_merge($preview['rows']->pluck('member_id')->all(), $preview['skipped'])) : [],
            'runs' => DividendRun::query()->with(['year', 'scheme', 'creator'])->latest('id')->limit(20)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAbility(Abilities::DIVIDEND_POST);
        $data = $request->validate([
            'financial_year_id' => ['required', 'integer'],
            'savings_scheme_id' => ['required', 'integer', 'exists:finance_savings_schemes,id'],
            'rate' => ['required', 'decimal:0,4', 'gt:0', 'max:100'],
            'posting_date' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $year = FinanceModels::financialYear()::query()->findOrFail($data['financial_year_id']);

        return $this->attempt(
            fn () => $this->dividends->post($year, (string) $data['rate'], SavingsScheme::query()->findOrFail($data['savings_scheme_id']), Carbon::parse($data['posting_date']), $request->user()),
            fn (DividendRun $run) => redirect()->route('finance.dividends.create')->with('success', __('Dividend of :total credited to :n accounts.', ['total' => Money::of($run->total)->format(), 'n' => $run->accounts_count])),
        );
    }
}
