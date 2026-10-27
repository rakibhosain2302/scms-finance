<?php

namespace SCMS\Finance\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Models\Concerns\HasCreator;

class FinancialYear extends Model
{
    use HasCreator;

    protected $table = 'financial_years';

    protected $fillable = ['name', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
            'status' => YearStatus::class,
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === YearStatus::Open;
    }

    public function contains(CarbonInterface|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return $date->betweenIncluded($this->starts_on, $this->ends_on);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', YearStatus::Open->value);
    }

    public function scopeContaining(Builder $query, CarbonInterface|string $date): Builder
    {
        $day = Carbon::parse($date)->toDateString();

        return $query->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day);
    }
}
