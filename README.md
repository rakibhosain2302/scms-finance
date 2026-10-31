# scms/finance

Double-entry finance and accounting for Laravel, built for SCMS (Samiti & Cooperative
Management System) and usable in any Laravel 12/13 app: chart of accounts, journal, ledger,
savings, loans, income, expense, cash, bank, transfers, year-end closing and reports.

Every money movement is a balanced, posted journal entry. Balances and reports are sums of
posted journal lines; nothing else is authoritative.

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Integration with nit-master](#integration-with-nit-master)
- [Integration with scms-core (members, branches, financial years)](#integration-with-scms-core)
- [Permissions](#permissions)
- [Accounting flow](#accounting-flow)
- [Modules](#modules)
- [Services (use from your own code)](#services)
- [Routes](#routes)
- [Database](#database)
- [Reports](#reports)
- [Testing](#testing)
- [Extending](#extending)
- [Limits and open points](#limits-and-open-points)

## Requirements

| | |
|---|---|
| PHP | 8.3+ (no `bcmath` needed: money math uses `brick/math`, already a Laravel dependency) |
| Laravel | 12 or 13 |
| Database | MySQL 8 / MariaDB 10.6+ (row locks, `CHECK` constraint). SQLite is not supported for posting. |
| UI | `nit/master` for the bundled screens (layout, Bootstrap 5 components, branches, roles). The services work without it. |

## Installation

In the application (SCMS uses path repositories):

```jsonc
// composer.json
"repositories": [
    { "type": "path", "url": "/var/www/NIT/packages/nit-master" },
    { "type": "path", "url": "/var/www/NIT/packages/scms-finance" }
],
"require": {
    "nit/master": "dev-master",
    "scms/finance": "@dev"
}
```

```bash
composer update scms/finance
php artisan migrate                 # 9 finance migrations; nit-master's run first (branches)
php artisan finance:install         # default chart of accounts (safe to repeat)
php artisan finance:install --demo  # + sample schemes, loan products, the current July–June year
```

The service provider `SCMS\Finance\SCMSFinanceServiceProvider` is auto-discovered.

Before the first transaction:

1. **Branches** – create at least one in *Authorization › Branches* (nit-master) and assign staff to them.
2. **Financial year** – *Finance › Accounting › Financial years* (or `--demo`).
3. **Cash / bank accounts** – *Finance › Cash & bank*, with their opening balances.
4. **Members** – set the member model (see [scms-core](#integration-with-scms-core)).
5. **Roles** – give the finance abilities to roles (see [Permissions](#permissions)).

## Configuration

```bash
php artisan vendor:publish --tag=scms-finance-config      # config/scms-finance.php
php artisan vendor:publish --tag=scms-finance-views       # resources/views/vendor/finance
php artisan vendor:publish --tag=scms-finance-migrations  # only if you need to edit them
```

| Key | Default | What it does |
|---|---|---|
| `enabled` | `true` | `false` turns the routes off (services, migrations stay) |
| `currency`, `decimal_places` | `BDT`, `2` | Scale of every money column and calculation |
| `pagination` | `20` | Rows per page |
| `routes.prefix` / `.name` / `.middleware` | `finance`, `finance.`, `['web','auth']` | Screens |
| `api.enabled` / `.prefix` / `.middleware` | `false`, `api/finance`, nit-master token auth | Read-only JSON |
| `layout` | `nit::master` | Layout every screen extends |
| `models.user/branch/member/financial_year` | auth model, nit-master `Branch`, `null`, package `FinancialYear` | Models of other packages |
| `member_columns` | `name`, `member_no`, `branch_id` | How members are shown, searched and tied to a branch |
| `tables.branches` / `.members` | `branches` / `null` | Foreign keys from finance tables |
| `migrate_financial_years` | `true` | `false` when another package owns `financial_years` |
| `numbering.pad` / `.per_branch` / `.prefixes` | `6`, `false`, DEP/WDR/LOAN/… | Transaction numbers |
| `accounts.*` | chart codes | Which account (by **code**, never id) the package posts to |
| `rules.prevent_negative_cash` | `true` | Refuse payments a drawer/bank cannot cover |
| `rules.backdate_days` | `null` | Limit how far back a transaction may be dated |
| `rules.loan_self_approval` | `false` | Whether the person who entered a loan may approve it |
| `rules.repayment_order` | `['interest','principal']` | Order inside each installment |
| `attachments.*` | `public` disk, 4 MB, pdf/images | Expense and income attachments |
| `sidebar` | `true` | Add the finance menu to nit-master's sidebar |

Environment: `SCMS_FINANCE_MEMBER_MODEL`, `SCMS_FINANCE_MEMBER_TABLE`, `SCMS_FINANCE_API`,
`SCMS_FINANCE_DISK`, `SCMS_FINANCE_CURRENCY`.

## Integration with nit-master

Nothing in nit-master is replaced or changed.

| Need | How finance uses nit-master |
|---|---|
| Sign-in, users, roles | Untouched. Routes use the app's `web` + `auth` middleware. |
| Abilities | Added to `config('ipermissions')` as groups *Finance: Accounting / Cash & Bank / Savings / Loans / Income & Expense / Reports*. They appear on *Authorization › Role* and work in `config/rolepermission.php`. |
| Authorization | `Gate` / `@can` / policies. nit-master's `Gate::before` grants super admins everything. |
| Branches | `branches` table and `Nit\Master\Models\Branch`; `Branches::forUser()` / `visibleIds()` / `defaultFor()` through `SCMS\Finance\Support\NitBranchAccess`. The header branch switcher filters finance lists. |
| Activity log | Every posting, reversal and loan decision is written with `Activity::log()` (`finance.deposit.posted`, `finance.transaction.reversed`, …). |
| Layout & UI | `nit::master`, `x-nit::card/table/input/select/textarea/button/badge`, toastr flashes, `data-nx-confirm` dialogs. |
| Menu | `config('sidebar.finance')`. Override by defining `finance` in the app's `config/sidebar.php`. |
| API tokens | The optional API uses nit-master's `AuthenticateApiToken`. |

**Why not `#[Permission]` + the `authorization` middleware?** nit-master's role form only
discovers controllers under `App\` and `Nit\`; package controllers (`SCMS\Finance\…`) would be
usable by super admins only. Finance therefore declares its abilities in `ipermissions` and
checks them itself, the way nit-master documents for "abilities that belong to no page".

## Integration with scms-core

At the time of writing no `scms-core` package exists, so finance owns nothing that belongs to it:

| Concept | Where it lives | Finance stores |
|---|---|---|
| Branch | nit-master `branches` | `branch_id` (FK, `RESTRICT`) |
| Member | `config('scms-finance.models.member')` (scms-core or the app) | `member_id` (FK only when `tables.members` is set) |
| Financial year | package `financial_years` table **unless** `migrate_financial_years=false` and `models.financial_year` points elsewhere | `financial_year_id` |
| User | the auth model | `created_by`, `posted_by`, … (no FK, so nit-master's user purge keeps working) |

Members are read through `SCMS\Finance\Contracts\MemberDirectory`:

```php
// .env
SCMS_FINANCE_MEMBER_MODEL="Scms\Core\Models\Member"
SCMS_FINANCE_MEMBER_TABLE=members

// config/scms-finance.php
'member_columns' => ['label' => 'name', 'number' => 'member_no', 'branch' => 'branch_id', 'search' => ['name', 'member_no']],
```

A member may only hold accounts and loans in their own branch (checked when `member_columns.branch` is set).
Until a member model is configured, the savings and loan forms show a notice and nothing can be opened.

To take financial years from scms-core: set `migrate_financial_years` to `false`, point
`models.financial_year` at its model (columns `name`, `starts_on`, `ends_on`, `status` =
`open`/`closed`), or bind your own `SCMS\Finance\Contracts\FinancialYearResolver`.

## Permissions

| Group | Ability | |
|---|---|---|
| Finance: Accounting | `finance_dashboard_view` | Finance dashboard |
| | `finance_account_view` / `finance_account_manage` | Chart of accounts |
| | `finance_year_manage` | Open and close financial years |
| | `finance_journal_view` / `_create` / `_post` | Manual journal entries |
| | `finance_transaction_reverse` | Reverse any posted document |
| Finance: Cash & Bank | `finance_cashbank_view` / `_manage` | Cash drawers, bank accounts |
| | `finance_transfer_view` / `_create` / `_post` | Transfers |
| Finance: Savings | `finance_savings_view` / `_manage` | Accounts, schemes, open/close |
| | `finance_savings_deposit` / `_withdraw` | Take deposits / pay withdrawals |
| Finance: Loans | `finance_loan_view` / `finance_loan_product_manage` | Loans, products |
| | `finance_loan_create` / `_approve` / `_disburse` / `_repay` | Lifecycle |
| Finance: Income & Expense | `finance_income_view` / `_create` / `_post`, same for `expense` | Vouchers |
| Finance: Reports | `finance_report_view` / `finance_report_all_branches` | Reports; all branches regardless of assignment |

Example roles in the app's `config/rolepermission.php`:

```php
return [
    'Cashier' => [
        'Finance: Savings::finance_savings_view', 'Finance: Savings::finance_savings_deposit', 'Finance: Savings::finance_savings_withdraw',
        'Finance: Loans::finance_loan_view', 'Finance: Loans::finance_loan_repay',
        'Finance: Income & Expense::finance_income_view', 'Finance: Income & Expense::finance_income_create',
        'Finance: Reports::finance_report_view',
    ],
    'Branch manager' => ['Finance: Savings::*', 'Finance: Loans::*', 'Finance: Income & Expense::*', 'Finance: Cash & Bank::*', 'Finance: Reports::finance_report_view'],
    'Accountant' => ['Finance: Accounting::*', 'Finance: Reports::*'],
];
```

Every action also checks the **branch** of the record (policies + services). A submitted
`branch_id`, `account_id`, `member_id`, `loan_id` or `cash_bank_account_id` is never trusted:
form requests limit `branch_id` to the user's branches, the global branch scope hides other
branches' records (route model binding returns 404), and the services verify that every
related record belongs to the same branch before posting.

> nit-master treats a user **without any branch** as unlimited. Assign every non-admin staff
> member to their branch.

## Accounting flow

```
Document (deposit, repayment, voucher, transfer…)      one DB transaction
   → JournalService::record()  balanced? accounts usable? year open? cash available?
      → finance_journal_entries (posted) + finance_journal_lines
         → LedgerService (balances)  → ReportService (reports)
```

Rules enforced in code **and** in the database:

- Total debit = total credit, > 0, at least two lines (`UnbalancedJournalException`).
- A line is a debit or a credit, never both, never negative (also a `CHECK` constraint).
- Only open financial years take postings (`ClosedFinancialYearException`), read with a shared lock so closing a year waits for running postings.
- Posted entries are never edited or deleted. A reversal posts the mirrored entry (`REV-…`, `reversal_of_id` unique → an entry is reversed at most once) and marks the original `reversed`; both stay in the ledger.
- Statuses: `draft → posted → reversed`, `draft → cancelled`.
- All money columns are `DECIMAL(15,2)`; all arithmetic is `SCMS\Finance\Support\Money` (exact decimals, half-up rounding). No floats.
- `savings_accounts.balance` and `loans.principal_outstanding` are caches written in the same transaction; `php artisan finance:rebuild-balances [--fix]` compares them with the ledger.

Postings per document:

| Document | Debit | Credit |
|---|---|---|
| Savings deposit | Cash/Bank | Member savings (liability) |
| Savings withdrawal | Member savings | Cash/Bank |
| Loan disbursement | Loan receivable (principal) | Cash/Bank (principal − fee), Fee income (fee) |
| Loan repayment | Cash/Bank | Loan receivable (principal), Interest income, Penalty income |
| Income | Cash/Bank | Income account |
| Expense | Expense account | Cash/Bank |
| Transfer | Destination cash/bank | Source cash/bank |
| Opening balance (cash/bank) | Cash/Bank | Opening balance equity |
| Year close | Income accounts | Expense accounts, Accumulated surplus (or the reverse for a deficit) |

Each line also carries its **component** (`deposit`, `principal`, `interest`, `penalty`, `fee`, …)
and **sub-ledger** ids (`cash_bank_account_id`, `savings_account_id`, `loan_id`, `member_id`),
so principal is never read as income and a deposit never as revenue.

### Concurrency

Lock order is the same everywhere: document row (savings account / loan / voucher) → number
counter → cash/bank accounts (in id order). Examples:

- Two withdrawals on one savings account: the second waits for the first and sees its balance.
- Two payments from one drawer: `assertCashAvailable()` locks the drawer row before reading its balance.
- Numbers: `finance_sequences` row `SELECT … FOR UPDATE` inside the posting transaction; unique indexes back it up.
- Loan repayments lock the loan and its unpaid installments.

## Modules

| Module | Screens | Notes |
|---|---|---|
| Chart of accounts | tree with balances, add/edit/delete-if-unused | Types asset/liability/equity/income/expense; sub-type (cash, bank, member_savings, loan_receivable, …); group vs postable; optional branch-only accounts; system accounts keep their code |
| Financial years | list, open, close | Close = closing entry per branch + lock |
| Journal | list/filter, write (drafts), post, cancel, reverse | Member ledgers can only move through savings/loan screens; cash lines need their drawer |
| Cash & bank | accounts with ledger balances, statement | Opening balance posted on creation |
| Transfers | draft/post/reverse | Same-branch accounts only |
| Savings | schemes, accounts, deposits, withdrawals, reversal, close | Min/max deposit, minimum balance, withdrawals allowed / per month |
| Loans | products, application, approve/reject/cancel, disburse, schedule, repayments, reversal | Interest none / flat / declining; processing fee; penalty taken with a repayment; maker-checker approval |
| Income / Expense | draft/post/reverse vouchers with attachment | Account type enforced |
| Dashboard | today's figures, balances, overdue | `DashboardService` reusable in the app's dashboard |
| Savings interest | preview and post per scheme, branch and period | Daily balance × rate ÷ 365; a period is credited once |
| Dividend | preview and post per closed year | Share balance on the last day × rate, out of accumulated surplus, into a savings scheme |
| Share capital | sell, refund, reverse | Equity (3300), member tagged; optional `SCMS_FINANCE_SHARE_PRICE` |
| Go-live | savings opening balance (on opening), running loans (form and CSV) | Against opening balance equity, no cash |
| Loan changes | reschedule, waive interest, write-off, recovery after write-off | Penalty rules per product (fixed / percent, grace days); guarantors and collateral |
| Bank reconciliation | statement, tick cleared lines, complete when they match | Nothing is posted |
| Large withdrawals | through nit-master Approvals above `SCMS_FINANCE_WITHDRAWAL_APPROVAL_ABOVE` | Checked when asked, posted when approved |
| SMS | after deposits, withdrawals, disbursements, repayments; `finance:loan-reminders` | `SCMS_FINANCE_SMS=true`, nit-master gateway |

## Services

All write methods run in one DB transaction, take the acting user explicitly (null in console
jobs = no branch check), and throw a `SCMS\Finance\Exceptions\FinanceException` subclass with a
message meant for the user when a rule says no.

```php
use SCMS\Finance\Services\{SavingsService, SavingsTransactionService, LoanService, LoanRepaymentService,
    IncomeService, ExpenseService, TransferService, JournalService, LedgerService, ReportService};

$account = app(SavingsService::class)->open([
    'branch_id' => $branchId, 'member_id' => $member->id, 'savings_scheme_id' => $scheme->id, 'opened_on' => '2026-10-27',
], $user);

$deposit = app(SavingsTransactionService::class)->deposit($account, [
    'amount' => '500.00', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $cash->id,
], $user);

$loan = app(LoanService::class)->apply([...], $officer);
app(LoanService::class)->approve($loan, $manager);
app(LoanService::class)->disburse($loan, ['disbursed_on' => ..., 'first_due_on' => ..., 'cash_bank_account_id' => ...], $cashier);
app(LoanRepaymentService::class)->repay($loan, ['amount' => '1120', 'penalty_amount' => '0', 'repayment_date' => ..., 'cash_bank_account_id' => ...], $cashier);

app(IncomeService::class)->create([...], $uploadedFileOrNull, post: true, user: $user);
app(TransferService::class)->create([...], post: true, user: $user);

// Any document: reverse with a reason
app(SavingsTransactionService::class)->reverse($deposit, 'Entered twice', $user);

// Your own module posting to the ledger
app(JournalService::class)->record(
    ['branch_id' => $branchId, 'entry_date' => today(), 'description' => 'Share capital', 'source_type' => 'share', 'source_id' => $share->id],
    [JournalService::debit($cashAccount, '1000', ['cash_bank_account_id' => $cash->id]), JournalService::credit($shareCapital, '1000')],
    $user,
);

// Balances
app(LedgerService::class)->savingsBalance($account->id);
app(LedgerService::class)->loanOutstanding($loan->id);
app(LedgerService::class)->cashBankBalance($cash->id, '2026-06-30');
```

| Service | Responsibility |
|---|---|
| `JournalService` | The only writer of journal entries: `record`, `createDraft/updateDraft/cancelDraft/post`, `reverse`, `reverseManual` |
| `LedgerService` | Balances, ledger with running balance, trial balance, totals by account/type |
| `TransactionNumberService` | `DEP-2026-000001`, lock-safe |
| `FinancialYearService` | Year of a date, open-year guard, back-dating rule, create (no overlaps) |
| `YearEndService` | Close a year |
| `AccountService`, `CashBankService` | Chart of accounts, cash/bank accounts with opening balance |
| `SavingsService`, `SavingsTransactionService` | Accounts; deposits, withdrawals, reversal |
| `LoanService`, `LoanRepaymentService` | Lifecycle; repayments with allocation and reversal |
| `IncomeService`, `ExpenseService` (`VoucherService`), `TransferService` | Vouchers and transfers |
| `ReportService`, `DashboardService` | Reports and dashboard figures |
| `BalanceRebuildService` | Cache vs ledger check |
| `Support\LoanScheduleCalculator`, `Support\RepaymentAllocator` | Pure schedule and allocation maths |

Events (dispatched **after commit**, so listeners never see a rolled-back posting):
`DepositPosted`, `WithdrawalPosted`, `LoanDisbursed`, `LoanRepaymentPosted`, `IncomePosted`,
`ExpensePosted`, `TransferPosted`, `JournalPosted`, `TransactionReversed`, `LoanStatusChanged`.
Accounting never depends on a listener.

## Routes

All under `/finance`, names `finance.*`, middleware `web, auth`:

| Area | Routes |
|---|---|
| Dashboard | `finance.dashboard` |
| Accounting | `accounts.*`, `financial-years.index/create/store/close`, `journals.*` + `post/cancel/reverse` |
| Cash & bank | `cash-bank.*`, `transfers.*` + `post/cancel/reverse` |
| Savings | `savings-schemes.*`, `savings-accounts.index/create/store/show/status`, `savings-transactions.index/create/store/show/reverse` |
| Loans | `loan-products.*`, `loans.index/create/store/show/preview/approve/reject/cancel/disburse(.create)/reverse-disbursement`, `loan-repayments.create/store/index/show/reverse` |
| Vouchers | `incomes.*`, `expenses.*` + `post/cancel/reverse` |
| Reports | `reports.index/activity/cashbook/ledger/trial-balance/income-statement/balance-sheet/loans/savings/branches` |
| API (opt-in) | `GET api/finance/dashboard, accounts, cash-bank, savings-accounts(/{id}), loans(/{id})` |

`php artisan route:list --name=finance` lists them all.

## Database

| Table | Holds |
|---|---|
| `financial_years` | Years (only when no other package provides them) |
| `finance_accounts` | Chart of accounts (self-referencing `parent_id`) |
| `finance_cash_bank_accounts` | Drawers and bank accounts per branch |
| `finance_sequences` | Number counters |
| `finance_journal_entries`, `finance_journal_lines` | The ledger |
| `finance_savings_schemes`, `finance_savings_accounts`, `finance_savings_transactions` | Savings |
| `finance_loan_products`, `finance_loans`, `finance_loan_installments`, `finance_loan_repayments`, `finance_loan_repayment_allocations` | Loans |
| `finance_incomes`, `finance_expenses`, `finance_transfers` | Vouchers |

Every foreign key is `RESTRICT`: nothing financial disappears through a cascade. Money is
`DECIMAL(15,2)`. Indexes cover branch + date, status + date, source, and every sub-ledger column.

## Reports

All read posted lines only and accept branch (limited to the user's, or all with
`finance_report_all_branches`), period / as-of date and their own filters; each prints from the browser.

Daily and monthly activity (deposits, withdrawals, disbursed, principal/interest/penalty/fees
received, income, expense, net change of cash & bank; per day, month or branch), cash book,
account ledger, trial balance, income & expenditure, balance sheet (with the not-yet-closed
surplus so it always balances), loan portfolio and overdue loans, savings per scheme, branch summary.

## Testing

```bash
cd packages/scms-finance
composer install
vendor/bin/phpunit                         # unit + package tests (no database)

# Database tests: an EMPTY MySQL/MariaDB database that may be wiped
FINANCE_TEST_DB_DATABASE=scms_finance_test FINANCE_TEST_DB_USERNAME=... FINANCE_TEST_DB_PASSWORD=... vendor/bin/phpunit
```

| Suite | Covers |
|---|---|
| Unit | `Money` exactness and rounding; loan schedules (none/flat/declining, weekly/monthly, rounding remainder); repayment allocation |
| PackageTest | Provider, routes, `auth` on every route, abilities and menu in nit-master, every Blade view compiles |
| AccountingTest | Balanced posting, unbalanced rejected, DB `CHECK`, posted immutable, draft edit/cancel, reversal (once, not of a reversal), member ledgers and group accounts protected |
| SavingsTest | Deposit = liability not income, withdrawal, overdraw, minimum balance, scheme limits, reversal guards, branch checks, closing |
| LoanTest | Maker-checker, limits, disbursement (fee, not expense), principal/interest/penalty split, partial, overpayment, completion, latest-only reversal, disbursement reversal, declining interest |
| IncomeExpenseTest | Journals, drafts, negative cash, account type, reversal |
| CashBankTest | Opening balance, cash↔bank, bank↔bank, checks, drafts |
| SecurityTest | 403 without ability, 404 across branches, forged `branch_id`, reverse ability, closed year, year close, overlapping years, every page renders |
| ReportTest | Activity nets reversals, trial balance and balance sheet balance, cash book, branch-limited reports, loan portfolio |
| ConcurrencyTest | Two real connections: a withdrawal waits for a lock on the account; number counter serialised and unique |

## Extending

- **Another branch system:** bind `SCMS\Finance\Contracts\BranchAccess`.
- **Another member source:** set `models.member` or bind `SCMS\Finance\Contracts\MemberDirectory`.
- **Another financial year model:** see [scms-core](#integration-with-scms-core) or bind `FinancialYearResolver`.
- **Different accounts:** change the chart; point `config('scms-finance.accounts')` at your codes. Products and schemes choose their own accounts.
- **Screens:** publish the views or set `layout`. **Menu:** define `sidebar.finance` in the app.
- **Your own documents:** call `JournalService::record()` with your `source_type`; reverse with `JournalService::reverse()`.
- **Notifications / SMS:** listen to the events above.

## Limits and open points

- Maker-checker: loans need another approver; drafts (journals, vouchers, transfers) are posted by
  someone other than their writer; large withdrawals go through nit-master `Approvals`.
- Interest income is booked when received (cash basis); waived interest is never income.
- Members are chosen from a select (500 per branch, with search); a remote search is needed for very large branches.
- Inter-branch transactions (a member of branch A paying in branch B) are not supported.
- Dates are compared in the app timezone; set `APP_TIMEZONE=Asia/Dhaka` so a 01:00 deposit lands on the right business day.
