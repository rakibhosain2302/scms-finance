<?php

namespace SCMS\Finance\Http\Requests;

/** Reversals and rejections need a reason, kept with the record. */
class ReasonRequest extends FinanceRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}
