@php($open = $loan->installments->filter(fn ($i) => $i->status !== \SCMS\Finance\Enums\InstallmentStatus::Paid))
@include('finance::loans.partials.modal', [
    'id' => 'fin-reschedule', 'button' => __('Reschedule'), 'icon' => 'fa-calendar-alt', 'title' => __('Reschedule :number', ['number' => $loan->loan_number]),
    'action' => route('finance.loans.reschedule', $loan), 'submit' => __('Reschedule'),
    'text' => __('What is still owed (:principal principal, :interest interest) is spread over the new installments. No new interest is added.', [
        'principal' => \SCMS\Finance\Support\Money::sum($open->map->principalRemaining()->all())->format(),
        'interest' => \SCMS\Finance\Support\Money::sum($open->map->interestRemaining()->all())->format(),
    ]),
    'fields' => new \Illuminate\Support\HtmlString(view('finance::loans.partials.fields', ['fields' => [
        ['installments_count', 'number', __('New installments'), $open->count() ?: 1],
        ['first_due_on', 'date', __('First new installment due'), today()->addMonthNoOverflow()->toDateString()],
        ['reason', 'textarea', __('Reason'), ''],
    ]])->render()),
])
