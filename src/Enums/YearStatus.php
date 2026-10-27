<?php

namespace SCMS\Finance\Enums;

enum YearStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }
}
