<?php

namespace SCMS\Finance\Support;

/** Classes of the models other packages provide, from config('scms-finance.models'). */
final class FinanceModels
{
    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public static function user(): string
    {
        return config('scms-finance.models.user')
            ?: config('auth.providers.'.config('auth.guards.web.provider', 'users').'.model')
            ?: \Illuminate\Foundation\Auth\User::class;
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public static function branch(): string
    {
        return config('scms-finance.models.branch') ?: \Nit\Master\Models\Branch::class;
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model>|null */
    public static function member(): ?string
    {
        $model = config('scms-finance.models.member');

        return $model && class_exists($model) ? $model : null;
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public static function financialYear(): string
    {
        return config('scms-finance.models.financial_year') ?: \SCMS\Finance\Models\FinancialYear::class;
    }
}
