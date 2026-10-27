<?php

namespace SCMS\Finance\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SCMS\Finance\Exceptions\FinanceException;

/**
 * Maker-checker for drafts (manual journals, income and expense vouchers, transfers): whoever wrote
 * a draft may not post it, so moving money always takes two people. Super admins are excepted (a
 * samiti may have one), and scms-finance.rules.self_posting turns the rule off. Console commands
 * and approval handlers run without a user: their caller decided.
 */
final class MakerChecker
{
    /** Whether the user may post drafts they wrote themselves. */
    public static function selfPostingAllowed(?Authenticatable $user): bool
    {
        return $user === null
            || (bool) config('scms-finance.rules.self_posting', false)
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin());
    }

    public static function mayPost(?Authenticatable $user, Model $draft): bool
    {
        return self::selfPostingAllowed($user)
            || (string) $draft->getAttribute('created_by') !== (string) $user->getAuthIdentifier();
    }

    public static function assertMayPost(?Authenticatable $user, Model $draft): void
    {
        if (! self::mayPost($user, $draft)) {
            throw new FinanceException(__('Someone other than the person who wrote :number must post it.', ['number' => $draft->getAttribute('number')]));
        }
    }
}
