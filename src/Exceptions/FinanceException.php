<?php

namespace SCMS\Finance\Exceptions;

use RuntimeException;

/**
 * A business rule said no. The message is written for the person at the screen; controllers
 * show it as a validation error and nothing is saved (every service runs in one transaction).
 */
class FinanceException extends RuntimeException
{
}
