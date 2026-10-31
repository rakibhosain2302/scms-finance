{{-- Plain form fields for the loan modals: [name, type, label, value, options?]. --}}
@foreach ($fields as [$name, $type, $label, $value])
    <div class="mb-3">
        <label for="fin-{{ $name }}" class="form-label">{{ $label }}</label>
        @if ($type === 'textarea')
            <textarea id="fin-{{ $name }}" name="{{ $name }}" class="form-control" rows="2" required maxlength="500">{{ $value }}</textarea>
        @elseif ($type === 'select')
            <select id="fin-{{ $name }}" name="{{ $name }}" class="form-select" required>
                @foreach (($fields[$loop->index][4] ?? []) as $id => $optionLabel)<option value="{{ $id }}">{{ $optionLabel }}</option>@endforeach
            </select>
        @else
            <input id="fin-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" class="form-control" @if ($name !== 'reference') required @endif>
        @endif
    </div>
@endforeach
