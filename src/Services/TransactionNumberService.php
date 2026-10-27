<?php

namespace SCMS\Finance\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Models\Sequence;
use SCMS\Finance\Support\FinanceModels;

/**
 * DEP-2026-000001. The counter row is read with SELECT … FOR UPDATE inside the caller's
 * transaction, so two requests never get the same number, and a rolled-back posting gives
 * its number back.
 */
class TransactionNumberService
{
    public function next(string $key, DateTimeInterface|string $date, ?int $branchId = null): string
    {
        $series = $this->series($key, $date, $branchId);

        return DB::transaction(function () use ($series) {
            DB::table('finance_sequences')->insertOrIgnore([
                'series' => $series, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $sequence = Sequence::query()->where('series', $series)->lockForUpdate()->firstOrFail();
            $sequence->last_number = (int) $sequence->last_number + 1;
            $sequence->save();

            return $series.'-'.str_pad((string) $sequence->last_number, (int) config('scms-finance.numbering.pad', 6), '0', STR_PAD_LEFT);
        });
    }

    public function series(string $key, DateTimeInterface|string $date, ?int $branchId = null): string
    {
        $prefix = config("scms-finance.numbering.prefixes.{$key}") ?: strtoupper($key);
        $parts = [$prefix];

        if (config('scms-finance.numbering.per_branch') && $branchId) {
            $branch = FinanceModels::branch()::query()->find($branchId);
            $parts[] = strtoupper((string) ($branch?->code ?: $branchId));
        }
        $parts[] = Carbon::parse($date)->format('Y');

        return implode('-', $parts);
    }
}
