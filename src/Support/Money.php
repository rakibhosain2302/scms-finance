<?php

namespace SCMS\Finance\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Exact decimal money. Amounts travel as strings ("1250.50"), as MySQL returns DECIMAL columns,
 * and every operation keeps the configured scale. Never a float.
 *
 *     Money::of('100.10')->plus('0.20')->toString();        // "100.30"
 *     Money::of($row->debit)->minus($row->credit)->isNegative();
 */
final class Money implements JsonSerializable, Stringable
{
    private function __construct(private readonly BigDecimal $value)
    {
    }

    public static function of(self|BigNumber|int|string|null $amount): self
    {
        if ($amount instanceof self) {
            return $amount;
        }
        if ($amount === null || $amount === '') {
            return self::zero();
        }
        if (is_string($amount)) {
            $amount = trim(str_replace(',', '', $amount));
            if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
                throw new InvalidArgumentException("Not an amount: {$amount}");
            }
        }

        return new self(BigDecimal::of($amount)->toScale(self::scale(), self::halfUp()));
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::scale()));
    }

    /** @param  iterable<self|string|int|null>  $amounts */
    public static function sum(iterable $amounts): self
    {
        $total = self::zero();
        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public static function scale(): int
    {
        // Also outside a booted application (plain unit tests): the default scale.
        return function_exists('app') && app()->bound('config') ? (int) config('scms-finance.decimal_places', 2) : 2;
    }

    public function plus(self|int|string|null $amount): self
    {
        return new self($this->value->plus(self::of($amount)->value));
    }

    public function minus(self|int|string|null $amount): self
    {
        return new self($this->value->minus(self::of($amount)->value));
    }

    /** Multiplied by a rate or count ("0.125", 3), rounded half up to the money scale. */
    public function times(BigNumber|int|string $factor): self
    {
        return new self($this->value->multipliedBy($factor)->toScale(self::scale(), self::halfUp()));
    }

    /** Divided, rounded half up to the money scale. */
    public function dividedBy(BigNumber|int|string $divisor): self
    {
        return new self($this->value->dividedBy($divisor, self::scale(), self::halfUp()));
    }

    /** Divided, rounded down: the remainder is left for the last of several parts. */
    public function dividedDown(int $divisor): self
    {
        return new self($this->value->dividedBy($divisor, self::scale(), self::down()));
    }

    public function negated(): self
    {
        return new self($this->value->negated());
    }

    public function abs(): self
    {
        return new self($this->value->abs());
    }

    public function compareTo(self|int|string|null $amount): int
    {
        return $this->value->compareTo(self::of($amount)->value);
    }

    public function equals(self|int|string|null $amount): bool
    {
        return $this->compareTo($amount) === 0;
    }

    public function greaterThan(self|int|string|null $amount): bool
    {
        return $this->compareTo($amount) > 0;
    }

    public function lessThan(self|int|string|null $amount): bool
    {
        return $this->compareTo($amount) < 0;
    }

    public static function min(self|int|string|null $a, self|int|string|null $b): self
    {
        return self::of($a)->lessThan($b) ? self::of($a) : self::of($b);
    }

    public static function max(self|int|string|null $a, self|int|string|null $b): self
    {
        return self::of($a)->greaterThan($b) ? self::of($a) : self::of($b);
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isPositive(): bool
    {
        return $this->value->isPositive();
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    /** "1,250.50" (or Bangla digits through nit-master when available). */
    public function format(): string
    {
        if (class_exists(\Nit\Master\Support\Format::class)) {
            return \Nit\Master\Support\Format::money($this->toString(), self::scale());
        }

        return number_format((float) $this->toString(), self::scale()); // display only
    }

    /** brick/math 1.x names its cases HalfUp; 0.12 – 0.14 HALF_UP. */
    public static function halfUp(): RoundingMode
    {
        return defined(RoundingMode::class.'::HalfUp') ? constant(RoundingMode::class.'::HalfUp') : constant(RoundingMode::class.'::HALF_UP');
    }

    private static function down(): RoundingMode
    {
        return defined(RoundingMode::class.'::Down') ? constant(RoundingMode::class.'::Down') : constant(RoundingMode::class.'::DOWN');
    }
}
