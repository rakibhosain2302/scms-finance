<?php

namespace SCMS\Finance\Enums;

enum SavingsAccountStatus: string
{
    case Active = 'active';
    case Dormant = 'dormant';
    case Closed = 'closed';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Dormant => 'warning',
            self::Closed => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
