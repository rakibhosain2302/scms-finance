<?php

namespace SCMS\Finance\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Support\FinanceModels;

/** created_by: filled from the signed-in user when the service did not set it. */
trait HasCreator
{
    public static function bootHasCreator(): void
    {
        static::creating(function ($model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(FinanceModels::user(), 'created_by');
    }

    /** nit-master's "own records only" (HasOwner::isOwnedBy) works with these models too. */
    public function isOwnedBy($user): bool
    {
        return $user !== null && (string) $this->created_by === (string) $user->getAuthIdentifier();
    }
}
