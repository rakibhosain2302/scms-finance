<?php

namespace SCMS\Finance\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SCMS\Finance\Support\Money;

class MoneyTest extends TestCase
{
    public function test_arithmetic_is_exact_where_floats_are_not(): void
    {
        // 0.1 + 0.2 is 0.30000000000000004 in floating point.
        $this->assertSame('0.30', Money::of('0.1')->plus('0.2')->toString());
        $this->assertSame('0.00', Money::of('1000000000000.10')->minus('1000000000000.10')->toString());
        $this->assertTrue(Money::sum(array_fill(0, 10, '0.10'))->equals('1.00'));
    }

    public function test_it_rounds_half_up_to_the_scale(): void
    {
        $this->assertSame('100.13', Money::of('100.125')->toString());
        $this->assertSame('33.33', Money::of('100')->dividedBy(3)->toString());
        $this->assertSame('12.35', Money::of('123.45')->times('0.1')->toString()); // 12.345
        $this->assertSame('33.33', Money::of('100')->dividedDown(3)->toString());
    }

    public function test_comparisons(): void
    {
        $this->assertTrue(Money::of('10.00')->equals('10'));
        $this->assertTrue(Money::of('10.01')->greaterThan('10'));
        $this->assertTrue(Money::of('-0.01')->isNegative());
        $this->assertTrue(Money::of(null)->isZero());
        $this->assertSame('5.00', Money::min('5', '7')->toString());
        $this->assertSame('7.00', Money::max('5', '7')->toString());
        $this->assertSame('1250.50', Money::of('1,250.50')->toString());
    }

    public function test_it_refuses_what_is_not_an_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of('12abc');
    }
}
