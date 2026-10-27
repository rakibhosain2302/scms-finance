<?php

namespace SCMS\Finance\Models\Concerns;

use SCMS\Finance\Support\Money;

/**
 * The attributes listed in $money are cast to exact decimal strings at the configured
 * scale ("1250.50"), never floats.
 */
trait HasMoneyAttributes
{
    public function initializeHasMoneyAttributes(): void
    {
        $this->mergeCasts(array_fill_keys($this->money ?? [], 'decimal:'.Money::scale()));
    }

    /** The attribute as Money. */
    public function money(string $attribute): Money
    {
        return Money::of($this->getAttribute($attribute));
    }
}
