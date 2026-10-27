<?php

namespace SCMS\Finance\Services;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Contracts\FinancialYearResolver;
use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Exceptions\ClosedFinancialYearException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Support\FinanceModels;

/** Financial years: which year a date belongs to, whether it is open, opening new ones. */
class FinancialYearService implements FinancialYearResolver
{
    public function forDate(DateTimeInterface|string $date): ?Model
    {
        return $this->containing($date)->first();
    }

    /**
     * The open year of the date, read with a shared lock: postings run side by side, while
     * closing the year (FOR UPDATE) waits for them and they wait for it.
     */
    public function openYearFor(DateTimeInterface|string $date): Model
    {
        $year = $this->containing($date)->sharedLock()->first();

        if (! $year) {
            throw new ClosedFinancialYearException(__('No financial year contains :date. Open one first.', ['date' => Carbon::parse($date)->toDateString()]));
        }
        if (($year->status instanceof YearStatus ? $year->status : YearStatus::tryFrom((string) $year->status)) !== YearStatus::Open) {
            throw new ClosedFinancialYearException(__('The financial year :name is closed.', ['name' => $year->name]));
        }

        return $year;
    }

    public function current(): ?Model
    {
        $open = YearStatus::Open->value;

        return $this->containing(today())->where('status', $open)->first()
            ?? FinanceModels::financialYear()::query()->where('status', $open)->orderByDesc('starts_on')->first();
    }

    /** Works with another package's financial year model too (only the columns are assumed). */
    private function containing(DateTimeInterface|string $date): Builder
    {
        $day = Carbon::parse($date)->toDateString();

        return FinanceModels::financialYear()::query()->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day);
    }

    /** Rejects a date too far in the past (scms-finance.rules.backdate_days) or in the future. */
    public function assertPostingDate(DateTimeInterface|string $date): void
    {
        $date = Carbon::parse($date)->startOfDay();

        if ($date->gt(today())) {
            throw new FinanceException(__('A transaction cannot be dated in the future.'));
        }

        $days = config('scms-finance.rules.backdate_days');
        if ($days !== null && $date->lt(today()->subDays((int) $days))) {
            throw new FinanceException(__('A transaction cannot be dated more than :days days back.', ['days' => $days]));
        }
    }

    public function create(array $data, ?Authenticatable $user = null): Model
    {
        $starts = Carbon::parse($data['starts_on'])->startOfDay();
        $ends = Carbon::parse($data['ends_on'])->startOfDay();
        if ($ends->lte($starts)) {
            throw new FinanceException(__('The year must end after it starts.'));
        }

        return DB::transaction(function () use ($data, $starts, $ends, $user) {
            $model = FinanceModels::financialYear();
            $overlaps = $model::query()->lockForUpdate()
                ->whereDate('starts_on', '<=', $ends->toDateString())
                ->whereDate('ends_on', '>=', $starts->toDateString())
                ->exists();
            if ($overlaps) {
                throw new FinanceException(__('The dates overlap another financial year.'));
            }

            $year = new $model(['name' => $data['name'], 'starts_on' => $starts, 'ends_on' => $ends]);
            $year->forceFill(['status' => YearStatus::Open->value, 'created_by' => $user?->getAuthIdentifier()])->save();

            return $year;
        });
    }
}
