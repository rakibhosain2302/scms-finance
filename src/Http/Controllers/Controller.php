<?php

namespace SCMS\Finance\Http\Controllers;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Gate;
use SCMS\Finance\Contracts\BranchAccess;
use SCMS\Finance\Contracts\MemberDirectory;
use SCMS\Finance\Exceptions\FinanceException;

/**
 * Thin controllers: authorize, hand validated input to a service, redirect. A business rule
 * that says no (FinanceException) comes back as an error on the form; nothing was saved.
 */
abstract class Controller extends BaseController
{
    protected function authorizeAbility(string $ability, mixed $arguments = []): void
    {
        Gate::authorize($ability, $arguments);
    }

    /** Runs the action; a refused business rule returns to the form with the message. */
    protected function attempt(Closure $action, Closure $then): RedirectResponse
    {
        try {
            $result = $action();
        } catch (FinanceException $e) {
            return back()->withInput()->withErrors(['finance' => $e->getMessage()]);
        }

        return $then($result);
    }

    protected function view(string $view, array $data = [])
    {
        return view('finance::'.$view, $data);
    }

    protected function perPage(): int
    {
        return (int) config('scms-finance.pagination', 20);
    }

    /** @return array<int, string> */
    protected function branchOptions(): array
    {
        return app(BranchAccess::class)->options(auth()->user());
    }

    protected function defaultBranch(): ?int
    {
        return app(BranchAccess::class)->defaultFor(auth()->user());
    }

    /** Branch a form is filled for: the record's, the one picked on the form, or the header's. */
    protected function formBranch(int|string|null $current = null): ?int
    {
        $picked = $current ?: (request()->old('branch_id') ?: request()->query('branch_id'));
        if ($picked && array_key_exists((int) $picked, $this->branchOptions())) {
            return (int) $picked;
        }

        return $current ? (int) $current : $this->defaultBranch();
    }

    /** Branches whose records the user sees now (null = all). */
    protected function visibleBranches(): ?array
    {
        return app(BranchAccess::class)->visibleIds(auth()->user());
    }

    protected function members(): MemberDirectory
    {
        return app(MemberDirectory::class);
    }
}
