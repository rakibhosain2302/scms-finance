@php($interest = \SCMS\Finance\Support\Money::sum($loan->installments->filter(fn ($i) => $i->status !== \SCMS\Finance\Enums\InstallmentStatus::Paid)->map->interestRemaining()->all()))
@include('finance::loans.partials.modal', [
    'id' => 'fin-waive', 'button' => __('Waive interest'), 'icon' => 'fa-hand-holding-heart', 'title' => __('Waive interest on :number', ['number' => $loan->loan_number]),
    'action' => route('finance.loans.waive', $loan), 'submit' => __('Waive'),
    'text' => __('For early settlement: interest still to come is :interest. The latest installments lose it first; then take the repayment as usual.', ['interest' => $interest->format()]),
    'fields' => new \Illuminate\Support\HtmlString(view('finance::loans.partials.fields', ['fields' => [
        ['amount', 'text', __('Interest to waive'), $interest->toString()],
        ['reason', 'textarea', __('Reason'), ''],
    ]])->render()),
])
