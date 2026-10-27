<?php

namespace SCMS\Finance\Enums;

/**
 * applied → approved → active (disbursed) → partially_paid → completed;
 * applied → rejected; applied / approved → cancelled; active / partially_paid → written_off.
 */
enum LoanStatus: string
{
    case Applied = 'applied';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Active = 'active';
    case PartiallyPaid = 'partially_paid';
    case Completed = 'completed';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::PartiallyPaid => __('Partially paid'),
            self::Active => __('Active (disbursed)'),
            self::WrittenOff => __('Written off'),
            default => __(ucfirst($this->value)),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Applied => 'warning',
            self::Approved, self::Active, self::PartiallyPaid => 'primary',
            self::Completed => 'success',
            self::Rejected, self::WrittenOff => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    /** Disbursed and not yet paid off. */
    public function isRunning(): bool
    {
        return in_array($this, [self::Active, self::PartiallyPaid], true);
    }

    /** @return list<string> */
    public static function runningValues(): array
    {
        return [self::Active->value, self::PartiallyPaid->value];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
