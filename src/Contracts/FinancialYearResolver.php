<?php

namespace SCMS\Finance\Contracts;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/** Finds the financial year of a date and guards closed years. */
interface FinancialYearResolver
{
    /** The year that contains the date, or null. */
    public function forDate(DateTimeInterface|string $date): ?Model;

    /**
     * The open year that contains the date.
     *
     * @throws \SCMS\Finance\Exceptions\ClosedFinancialYearException
     */
    public function openYearFor(DateTimeInterface|string $date): Model;

    /** The open year that contains today, or the latest open one. */
    public function current(): ?Model;
}
