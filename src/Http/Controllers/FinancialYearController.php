<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use SCMS\Finance\Http\Requests\FinancialYearRequest;
use SCMS\Finance\Services\FinancialYearService;
use SCMS\Finance\Services\YearEndService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\FinanceModels;

class FinancialYearController extends Controller
{
    public function index()
    {
        $this->authorizeAbility(Abilities::YEAR_MANAGE);

        return $this->view('years.index', ['years' => FinanceModels::financialYear()::query()->orderByDesc('starts_on')->paginate($this->perPage())]);
    }

    public function create()
    {
        $this->authorizeAbility(Abilities::YEAR_MANAGE);

        $last = FinanceModels::financialYear()::query()->orderByDesc('ends_on')->first();
        $starts = $last ? $last->ends_on->copy()->addDay() : today()->month(7)->startOfMonth()->subYears(today()->month >= 7 ? 0 : 1);

        return $this->view('years.create', ['starts' => $starts, 'ends' => $starts->copy()->addYear()->subDay()]);
    }

    public function store(FinancialYearRequest $request, FinancialYearService $years)
    {
        $this->authorizeAbility(Abilities::YEAR_MANAGE);

        return $this->attempt(
            fn () => $years->create($request->validated(), $request->user()),
            fn ($year) => redirect()->route('finance.financial-years.index')->with('success', __('Financial year :name opened.', ['name' => $year->name])),
        );
    }

    public function close(Request $request, YearEndService $yearEnd, int $financialYear)
    {
        $this->authorizeAbility(Abilities::YEAR_MANAGE);
        $year = FinanceModels::financialYear()::query()->findOrFail($financialYear);

        return $this->attempt(
            fn () => $yearEnd->close($year, $request->user()),
            fn (array $entries) => redirect()->route('finance.financial-years.index')
                ->with('success', __('Financial year :name closed with :n closing entries.', ['name' => $year->name, 'n' => count($entries)])),
        );
    }
}
