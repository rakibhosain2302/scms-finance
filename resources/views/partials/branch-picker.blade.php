{{-- Branch select on a new record; changing it reloads the form so its accounts follow the branch. --}}
@if (count($branches) > 1)
    <div class="nx-field">
        <label for="branch_id">{{ __('Branch') }} <span class="nx-req" aria-hidden="true">*</span></label>
        <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required
            onchange="var u = new URL(window.location.href); u.searchParams.set('branch_id', this.value); window.location = u;">
            @foreach ($branches as $id => $name)
                <option value="{{ $id }}" @selected((int) $branch === (int) $id)>{{ $name }}</option>
            @endforeach
        </select>
        @error('branch_id')<p class="nx-error">{{ $message }}</p>@enderror
    </div>
@elseif (count($branches) === 1)
    <input type="hidden" name="branch_id" value="{{ array_key_first($branches) }}">
@else
    <div class="nx-alert nx-full" role="alert"><i class="fas fa-code-branch mt-1" aria-hidden="true"></i> <span>{{ __('You are not assigned to any branch. Ask an administrator (Authorization › Branches).') }}</span></div>
@endif
