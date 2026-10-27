<?php

namespace SCMS\Finance\Enums;

/** What part of a transaction a journal line carries, so principal is never read as income. */
enum LineComponent: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Principal = 'principal';
    case Interest = 'interest';
    case Penalty = 'penalty';
    case Fee = 'fee';
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Payment = 'payment';
    case Opening = 'opening';
    case Closing = 'closing';
    case WriteOff = 'write_off';
    // Interest credited to a savings account (kept apart from loan interest income).
    case SavingsInterest = 'savings_interest';
}
