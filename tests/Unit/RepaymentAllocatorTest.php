<?php

namespace SCMS\Finance\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SCMS\Finance\Support\RepaymentAllocator;

class RepaymentAllocatorTest extends TestCase
{
    private array $installments = [
        ['id' => 1, 'principal_remaining' => '100.00', 'interest_remaining' => '10.00'],
        ['id' => 2, 'principal_remaining' => '100.00', 'interest_remaining' => '10.00'],
        ['id' => 3, 'principal_remaining' => '100.00', 'interest_remaining' => '10.00'],
    ];

    public function test_oldest_installment_first_interest_before_principal(): void
    {
        $r = (new RepaymentAllocator())->allocate($this->installments, '150');

        $this->assertSame('130.00', $r['principal']->toString());
        $this->assertSame('20.00', $r['interest']->toString());
        $this->assertSame('0.00', $r['leftover']->toString());
        $this->assertCount(2, $r['allocations']);
        $this->assertSame(2, $r['allocations'][1]['id']);
        $this->assertSame('30.00', $r['allocations'][1]['principal']->toString());
        $this->assertSame('10.00', $r['allocations'][1]['interest']->toString());
    }

    public function test_a_partial_payment_covers_interest_first(): void
    {
        $r = (new RepaymentAllocator())->allocate($this->installments, '6');

        $this->assertSame('6.00', $r['interest']->toString());
        $this->assertTrue($r['principal']->isZero());
    }

    public function test_principal_first_when_configured(): void
    {
        $r = (new RepaymentAllocator())->allocate($this->installments, '105', ['principal', 'interest']);

        $this->assertSame('100.00', $r['allocations'][0]['principal']->toString());
        $this->assertSame('5.00', $r['allocations'][0]['interest']->toString());
    }

    public function test_more_than_owed_is_returned_as_leftover_and_nothing_goes_negative(): void
    {
        $r = (new RepaymentAllocator())->allocate($this->installments, '400');

        $this->assertSame('70.00', $r['leftover']->toString());
        $this->assertSame('300.00', $r['principal']->toString());
        $this->assertSame('30.00', $r['interest']->toString());
    }

    public function test_already_paid_parts_take_nothing(): void
    {
        $r = (new RepaymentAllocator())->allocate([['id' => 9, 'principal_remaining' => '0', 'interest_remaining' => '0'], ...$this->installments], '10');

        $this->assertSame(1, $r['allocations'][0]['id']);
    }
}
