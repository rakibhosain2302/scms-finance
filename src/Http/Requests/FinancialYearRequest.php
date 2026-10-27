<?php

namespace SCMS\Finance\Http\Requests;

use SCMS\Finance\Support\Abilities;

class FinancialYearRequest extends FinanceRequest
{
    protected function ability(): ?string
    {
        return Abilities::YEAR_MANAGE;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:financial_years,name'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }
}
