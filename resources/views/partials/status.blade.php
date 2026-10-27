{{-- @include('finance::partials.status', ['status' => $record->status]) : EntryStatus, LoanStatus, SavingsAccountStatus or InstallmentStatus --}}
<x-nit::badge :tone="$status->tone()">{{ $status->label() }}</x-nit::badge>
