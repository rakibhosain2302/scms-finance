<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Models\JournalEntry;
use SCMS\Finance\Support\Abilities;

class JournalEntryPolicy extends FinancePolicy
{
    public function view(Authenticatable $user, JournalEntry $entry): bool
    {
        return $this->allows($user, Abilities::JOURNAL_VIEW, $entry);
    }

    public function update(Authenticatable $user, JournalEntry $entry): bool
    {
        return $entry->isManual() && $entry->status === EntryStatus::Draft && $this->allows($user, Abilities::JOURNAL_CREATE, $entry);
    }

    public function post(Authenticatable $user, JournalEntry $entry): bool
    {
        return $entry->isManual() && $entry->status === EntryStatus::Draft && $this->allows($user, Abilities::JOURNAL_POST, $entry);
    }

    public function reverse(Authenticatable $user, JournalEntry $entry): bool
    {
        return $entry->isManual() && $entry->status === EntryStatus::Posted && ! $entry->reversal_of_id && $this->allows($user, Abilities::REVERSE, $entry);
    }
}
