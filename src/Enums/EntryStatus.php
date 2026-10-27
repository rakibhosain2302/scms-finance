<?php

namespace SCMS\Finance\Enums;

/**
 * Life of every money document: drafts may change or go away; posted ones are final and are
 * corrected only by a reversal, which leaves both entries in the ledger.
 */
enum EntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Posted => 'success',
            self::Reversed => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
