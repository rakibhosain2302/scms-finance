<?php

namespace SCMS\Finance\Enums;

/** Overdue is not stored: it is "not paid and due before today" (LoanInstallment::scopeOverdue). */
enum InstallmentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Partial => 'warning',
            self::Paid => 'success',
        };
    }
}
