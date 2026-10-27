<?php

namespace SCMS\Finance\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Enums\InterestMethod;
use SCMS\Finance\Support\LoanScheduleCalculator;
use SCMS\Finance\Support\Money;

class LoanScheduleCalculatorTest extends TestCase
{
    private LoanScheduleCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new LoanScheduleCalculator();
    }

    public function test_without_interest_the_principal_is_split_and_the_last_takes_the_remainder(): void
    {
        $s = $this->calc->build('1000', InterestMethod::None, '0', 3, Frequency::Monthly, Carbon::parse('2026-01-31'));

        $this->assertSame(['333.33', '333.33', '333.34'], array_map(fn ($r) => $r['principal']->toString(), $s));
        $this->assertTrue($this->calc->totalInterest($s)->isZero());
        // Month ends do not overflow into the next month.
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_map(fn ($r) => $r['due_date']->toDateString(), $s));
    }

    public function test_flat_interest_is_on_the_full_principal_for_the_term(): void
    {
        $s = $this->calc->build('10000', InterestMethod::Flat, '12', 12, Frequency::Monthly, Carbon::parse('2026-02-01'));

        $this->assertSame('10000.00', Money::sum(array_column($s, 'principal'))->toString());
        $this->assertSame('1200.00', $this->calc->totalInterest($s)->toString());
        $this->assertSame('100.00', $s[0]['interest']->toString());
        $this->assertSame('833.37', $s[11]['principal']->toString());
    }

    public function test_flat_interest_for_a_weekly_loan_uses_the_term_in_years(): void
    {
        // 52 weeks at 10 % = one year = 10 % of the principal.
        $s = $this->calc->build('5200', InterestMethod::Flat, '10', 52, Frequency::Weekly, Carbon::parse('2026-01-05'));

        $this->assertSame('520.00', $this->calc->totalInterest($s)->toString());
        $this->assertSame('2026-01-12', $s[1]['due_date']->toDateString());
    }

    public function test_declining_balance_gives_equal_installments_and_repays_the_exact_principal(): void
    {
        $s = $this->calc->build('10000', InterestMethod::Declining, '12', 12, Frequency::Monthly, Carbon::parse('2026-02-01'));

        $this->assertSame('10000.00', Money::sum(array_column($s, 'principal'))->toString());
        // Standard amortisation: 888.49 a month, first month's interest 1 % of 10,000.
        $this->assertSame('100.00', $s[0]['interest']->toString());
        $this->assertSame('888.49', $s[0]['principal']->plus($s[0]['interest'])->toString());
        // Interest falls as the principal is repaid.
        $this->assertTrue($s[11]['interest']->lessThan($s[0]['interest']));
        foreach ($s as $row) {
            $this->assertFalse($row['principal']->isNegative());
        }
    }

    public function test_a_zero_rate_on_an_interest_product_behaves_like_no_interest(): void
    {
        $s = $this->calc->build('900', InterestMethod::Declining, '0', 3, Frequency::Monthly, Carbon::parse('2026-01-01'));

        $this->assertSame(['300.00', '300.00', '300.00'], array_map(fn ($r) => $r['principal']->toString(), $s));
    }
}
