@include('finance::loans.partials.modal', [
    'id' => 'fin-recover', 'button' => __('Record recovery'), 'icon' => 'fa-coins', 'title' => __('Money recovered on :number', ['number' => $loan->loan_number]),
    'action' => route('finance.loans.recover', $loan), 'submit' => __('Post recovery'), 'variant' => 'primary',
    'text' => __('Booked as income (recovery of written-off loans); the loan stays written off.'),
    'fields' => new \Illuminate\Support\HtmlString(view('finance::loans.partials.fields', ['fields' => [
        ['amount', 'text', __('Amount'), ''],
        ['recovery_date', 'date', __('Date'), today()->toDateString()],
        ['cash_bank_account_id', 'select', __('Received into'), '', \SCMS\Finance\Models\CashBankAccount::options($loan->branch_id)],
        ['reference', 'text', __('Reference'), ''],
    ]])->render()),
])
