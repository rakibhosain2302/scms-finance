{{-- Write-off button with a modal asking for the date and the reason. --}}
<button type="button" class="nx-btn nx-btn-danger" data-bs-toggle="modal" data-bs-target="#fin-write-off">
    <i class="fas fa-ban" aria-hidden="true"></i> {{ __('Write off') }}
</button>
<div class="modal fade" id="fin-write-off" tabindex="-1" aria-labelledby="fin-write-off-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('finance.loans.write-off', $loan) }}" class="modal-content" data-nx-once>
            @csrf
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="fin-write-off-title">{{ __('Write off :number', ['number' => $loan->loan_number]) }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="nx-muted">{{ __('The principal still owed (:amount) is booked as a loan loss and the loan is closed. No more repayments can be taken. This cannot be undone.', ['amount' => $outstanding->format()]) }}</p>
                <x-nit::input name="written_off_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-nit::textarea name="reason" :label="__('Reason')" rows="3" required maxlength="500" />
            </div>
            <div class="modal-footer">
                <button type="button" class="nx-btn nx-btn-light" data-bs-dismiss="modal">{{ __('Keep') }}</button>
                <button type="submit" class="nx-btn nx-btn-danger">{{ __('Write off') }}</button>
            </div>
        </form>
    </div>
</div>
