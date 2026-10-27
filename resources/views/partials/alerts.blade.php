{{-- A refused business rule (key "finance") and any error not shown beside a field. --}}
@if ($errors->any())
    <div class="nx-alert mb-5" role="alert">
        <i class="fas fa-exclamation-circle mt-1" aria-hidden="true"></i>
        <span>{{ $errors->first('finance') ?: $errors->first() }}</span>
    </div>
@endif
