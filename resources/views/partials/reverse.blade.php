{{-- Reverse button with a modal asking for the reason. @include('finance::partials.reverse', ['action' => route(...), 'label' => $record->number]) --}}
@php($modalId = 'fin-reverse-'.md5($action))
<button type="button" class="nx-btn nx-btn-danger" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
    <i class="fas fa-undo" aria-hidden="true"></i> {{ $button ?? __('Reverse') }}
</button>
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ $action }}" class="modal-content" data-nx-once>
            @csrf
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $modalId }}-title">{{ $title ?? __('Reverse :label', ['label' => $label]) }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="nx-muted">{{ $text ?? __('A mirrored entry is posted today; the original stays in the ledger, marked reversed. This cannot be undone.') }}</p>
                <x-nit::textarea name="reason" :label="__('Reason')" rows="3" required maxlength="500" />
            </div>
            <div class="modal-footer">
                <button type="button" class="nx-btn nx-btn-light" data-bs-dismiss="modal">{{ __('Keep') }}</button>
                <button type="submit" class="nx-btn nx-btn-danger">{{ $confirm ?? __('Reverse') }}</button>
            </div>
        </form>
    </div>
</div>
