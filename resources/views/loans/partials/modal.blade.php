{{-- A button and a Bootstrap modal holding a small form. Variables: id, button, icon, variant, title, action, text, submit, slot (fields). --}}
<button type="button" class="nx-btn nx-btn-{{ $variant ?? 'light' }}" data-bs-toggle="modal" data-bs-target="#{{ $id }}">
    <i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $button }}
</button>
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ $action }}" class="modal-content" data-nx-once>
            @csrf
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $id }}-title">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                @if ($text ?? null)<p class="nx-muted">{{ $text }}</p>@endif
                {{ $fields }}
            </div>
            <div class="modal-footer">
                <button type="button" class="nx-btn nx-btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="nx-btn nx-btn-primary">{{ $submit }}</button>
            </div>
        </form>
    </div>
</div>
