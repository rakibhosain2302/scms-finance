<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Http\Requests\ReasonRequest;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Models\LoanRecovery;
use SCMS\Finance\Services\LoanAdjustmentService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\FinanceModels;
use SCMS\Finance\Support\Money;
use Throwable;

/** Running loans brought in at go-live, rescheduling, interest waivers and recoveries after a write-off. */
class LoanAdjustmentController extends Controller
{
    /** CSV columns of the bulk import, in this order. */
    public const IMPORT_COLUMNS = ['branch_code', 'member_no', 'product_code', 'outstanding_principal', 'installments_left', 'next_due_on', 'opened_on', 'original_principal', 'reference'];

    public function __construct(private readonly LoanAdjustmentService $service)
    {
    }

    public function importForm(Request $request)
    {
        $this->authorizeAbility(Abilities::LOAN_IMPORT);
        $branch = $this->formBranch();

        return $this->view('loans.import', [
            'branches' => $this->branchOptions(),
            'branch' => $branch,
            'memberSearch' => $request->query('member_q'),
            'members' => $this->members()->options($branch ? [$branch] : $this->visibleBranches(), $request->query('member_q')),
            'membersAvailable' => $this->members()->available(),
            'products' => LoanProduct::query()->orderBy('name')->pluck('name', 'id')->all(),
            'columns' => self::IMPORT_COLUMNS,
            'report' => session('import_report'),
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizeAbility(Abilities::LOAN_IMPORT);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in(array_keys($this->branchOptions()))],
            'member_id' => ['required', 'integer'],
            'loan_product_id' => ['required', 'integer', 'exists:finance_loan_products,id'],
            'outstanding_principal' => ['required', 'decimal:0,'.Money::scale(), 'gt:0'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'next_due_on' => ['required', 'date'],
            'opened_on' => ['required', 'date', 'before_or_equal:today'],
            'original_principal' => ['nullable', 'decimal:0,'.Money::scale(), 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->attempt(
            fn () => $this->service->importRunning($data, $request->user()),
            fn (Loan $loan) => redirect()->route('finance.loans.show', $loan)->with('success', __('Loan :number brought in.', ['number' => $loan->loan_number])),
        );
    }

    /** Bulk import from CSV; with "check only" every row is tried and rolled back. */
    public function importCsv(Request $request)
    {
        $this->authorizeAbility(Abilities::LOAN_IMPORT);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $dryRun = $request->boolean('dry_run');

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);
        if (array_diff(['branch_code', 'member_no', 'product_code', 'outstanding_principal', 'installments_left', 'next_due_on', 'opened_on'], $header) !== []) {
            fclose($handle);

            return back()->withErrors(['file' => __('The first row must name the columns: :columns.', ['columns' => implode(', ', self::IMPORT_COLUMNS)])]);
        }

        $branches = FinanceModels::branch()::query()->whereIn('id', array_keys($this->branchOptions()))->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper((string) $code) => $id]);
        $memberModel = FinanceModels::member();
        $numberColumn = config('scms-finance.member_columns.number', 'member_no');
        $report = ['dry_run' => $dryRun, 'created' => 0, 'errors' => []];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $values = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            try {
                $branchId = $branches[strtoupper(trim((string) $values['branch_code']))] ?? throw new FinanceException(__('Unknown branch code or not one of yours.'));
                $member = $memberModel ? $memberModel::query()->withoutGlobalScopes()->where($numberColumn, trim((string) $values['member_no']))->first() : null;
                $product = LoanProduct::query()->where('code', trim((string) $values['product_code']))->first();
                if (! $member || ! $product) {
                    throw new FinanceException(__('Unknown member number or product code.'));
                }

                DB::beginTransaction();
                try {
                    $this->service->importRunning([
                        'branch_id' => $branchId, 'member_id' => $member->getKey(), 'loan_product_id' => $product->id,
                        'outstanding_principal' => trim((string) $values['outstanding_principal']), 'installments_count' => (int) $values['installments_left'],
                        'next_due_on' => trim((string) $values['next_due_on']), 'opened_on' => trim((string) $values['opened_on']),
                        'original_principal' => trim((string) ($values['original_principal'] ?? '')) ?: null, 'reference' => trim((string) ($values['reference'] ?? '')) ?: null,
                    ], $request->user());
                    $dryRun ? DB::rollBack() : DB::commit();
                    $report['created']++;
                } catch (Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
            } catch (FinanceException|\InvalidArgumentException|\Carbon\Exceptions\InvalidFormatException $e) {
                $report['errors'][] = ['line' => $line, 'reason' => $e->getMessage()];
            }
        }
        fclose($handle);

        return redirect()->route('finance.loans.import')->with('import_report', $report);
    }

    public function reschedule(ReasonRequest $request, Loan $loan)
    {
        $this->authorizeAbility('reschedule', $loan);
        $data = $request->validate(['installments_count' => ['required', 'integer', 'min:1', 'max:1000'], 'first_due_on' => ['required', 'date', 'after:today']]);

        return $this->attempt(
            fn () => $this->service->reschedule($loan, $data + ['reason' => $request->validated('reason')], $request->user()),
            fn () => redirect()->route('finance.loans.show', $loan)->with('success', __('Loan rescheduled.')),
        );
    }

    public function waive(ReasonRequest $request, Loan $loan)
    {
        $this->authorizeAbility('waive', $loan);
        $amount = $request->validate(['amount' => ['required', 'decimal:0,'.Money::scale(), 'gt:0']])['amount'];

        return $this->attempt(
            fn () => $this->service->waiveInterest($loan, $amount, $request->validated('reason'), $request->user()),
            fn () => redirect()->route('finance.loans.show', $loan)->with('success', __('Interest waived.')),
        );
    }

    public function recover(Request $request, Loan $loan)
    {
        $this->authorizeAbility('recover', $loan);
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,'.Money::scale(), 'gt:0'],
            'recovery_date' => ['required', 'date', 'before_or_equal:today'],
            'cash_bank_account_id' => ['required', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->attempt(
            fn () => $this->service->recover($loan, $data, $request->user()),
            fn (LoanRecovery $r) => redirect()->route('finance.loans.show', $loan)->with('success', __('Recovery :number posted.', ['number' => $r->number])),
        );
    }

    public function reverseRecovery(ReasonRequest $request, LoanRecovery $recovery)
    {
        $this->authorizeAbility(Abilities::REVERSE);
        abort_unless(app(\SCMS\Finance\Contracts\BranchAccess::class)->canAccess($request->user(), $recovery->branch_id), 404);

        return $this->attempt(
            fn () => $this->service->reverseRecovery($recovery, $request->validated('reason'), $request->user()),
            fn () => redirect()->route('finance.loans.show', $recovery->loan_id)->with('success', __(':number reversed.', ['number' => $recovery->number])),
        );
    }

    /** @return array<int, string> */
    public static function cashAccounts(Loan $loan): array
    {
        return CashBankAccount::options($loan->branch_id);
    }
}
