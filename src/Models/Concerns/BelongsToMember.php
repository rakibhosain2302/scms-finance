<?php

namespace SCMS\Finance\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Contracts\MemberDirectory;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Support\FinanceModels;

/** member_id points at the member model of another package (scms-finance.models.member). */
trait BelongsToMember
{
    public function member(): BelongsTo
    {
        $model = FinanceModels::member() ?? throw new FinanceException(__('No member model is configured (scms-finance.models.member).'));

        return $this->belongsTo($model, 'member_id')->withoutGlobalScopes();
    }

    public function memberLabel(): string
    {
        $directory = app(MemberDirectory::class);

        return $directory->available() ? $directory->label($directory->find($this->member_id)) : '#'.$this->member_id;
    }
}
