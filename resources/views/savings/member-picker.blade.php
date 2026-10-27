{{-- Member select of the branch, with a search that reloads the form. --}}
@if (! $membersAvailable)
    <div class="nx-alert nx-full" role="alert"><i class="fas fa-users mt-1" aria-hidden="true"></i>
        <span>{{ __('No member model is configured. Set SCMS_FINANCE_MEMBER_MODEL (scms-finance.models.member) to the member model of your app or scms-core.') }}</span></div>
@else
    <div class="nx-field">
        <label for="member_id">{{ __('Member') }} <span class="nx-req" aria-hidden="true">*</span></label>
        <select id="member_id" name="member_id" class="form-select @error('member_id') is-invalid @enderror" required>
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($members as $id => $label)
                <option value="{{ $id }}" @selected((string) old('member_id', request('member_id')) === (string) $id)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="input-group input-group-sm mt-2 fin-no-print">
            <input type="search" id="fin-member-q" class="form-control" value="{{ $memberSearch }}" placeholder="{{ __('Find a member by name or number') }}" aria-label="{{ __('Find a member') }}">
            <button type="button" class="nx-btn nx-btn-light" onclick="var u = new URL(window.location.href); u.searchParams.set('member_q', document.getElementById('fin-member-q').value); window.location = u;"><i class="fas fa-search" aria-hidden="true"></i></button>
        </div>
        @error('member_id')<p class="nx-error">{{ $message }}</p>@enderror
    </div>
@endif
