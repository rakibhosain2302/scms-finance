@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Loan application'))
@section('nx-inline-errors', '1')
@include('finance::partials.styles')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ route('finance.loans.store') }}" data-nx-once novalidate id="fin-loan">
        @csrf
        <x-nit::card :title="__('Loan application')" :subtitle="__('Someone else approves it; then it can be disbursed.')">
            <div class="nx-form-grid">
                @include('finance::partials.branch-picker')
                @include('finance::savings.member-picker')
                <div class="nx-field">
                    <label for="loan_product_id">{{ __('Product') }} <span class="nx-req" aria-hidden="true">*</span></label>
                    <select id="loan_product_id" name="loan_product_id" class="form-select @error('loan_product_id') is-invalid @enderror" required>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" @selected((string) old('loan_product_id') === (string) $p->id)>{{ $p->name }} — {{ $p->interest_method->label() }}@if ($p->interest_method !== \SCMS\Finance\Enums\InterestMethod::None) {{ rtrim(rtrim($p->interest_rate, '0'), '.') }}%@endif, {{ $p->repayment_frequency->label() }} {{ $p->min_installments }}–{{ $p->max_installments }}</option>
                        @endforeach
                    </select>
                    @error('loan_product_id')<p class="nx-error">{{ $message }}</p>@enderror
                </div>
                <x-nit::input name="principal" inputmode="decimal" :label="__('Amount')" required />
                <x-nit::input name="installments_count" type="number" min="1" :label="__('Installments')" required />
                <x-nit::input name="applied_on" type="date" :label="__('Applied on')" :value="today()->toDateString()" required />
                <x-nit::input name="purpose" :label="__('Purpose')" wrapper-class="nx-full" maxlength="500" />
            </div>
            <div class="mt-5">
                <button type="button" class="nx-btn nx-btn-light" id="fin-preview"><i class="fas fa-calendar-alt" aria-hidden="true"></i> {{ __('Show schedule') }}</button>
                <div id="fin-schedule" class="mt-3" aria-live="polite"></div>
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.loans.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Save application') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection

@push('js')
<script>
    document.getElementById('fin-preview').addEventListener('click', function () {
        var form = document.getElementById('fin-loan'), box = document.getElementById('fin-schedule');
        var q = new URLSearchParams({ loan_product_id: form.loan_product_id.value, principal: form.principal.value, installments_count: form.installments_count.value });
        box.textContent = '…';
        fetch(@json(route('finance.loans.preview')) + '?' + q, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok) { box.textContent = res.j.message || @json(__('Check the product, amount and installments.')); return; }
                var t = document.createElement('table'); t.className = 'nx-tbl';
                t.innerHTML = '<thead><tr><th>#</th><th>' + @json(__('Due')) + '</th><th class="fin-num">' + @json(__('Principal')) + '</th><th class="fin-num">' + @json(__('Interest')) + '</th><th class="fin-num">' + @json(__('Installment')) + '</th></tr></thead>';
                var body = document.createElement('tbody');
                res.j.installments.forEach(function (i) {
                    var tr = document.createElement('tr');
                    [i.no, i.due_date, i.principal, i.interest, i.total].forEach(function (v, k) { var td = document.createElement('td'); td.textContent = v; if (k > 1) td.className = 'fin-num'; tr.appendChild(td); });
                    body.appendChild(tr);
                });
                t.appendChild(body); box.innerHTML = ''; box.appendChild(t);
            })
            .catch(function () { box.textContent = @json(__('The schedule could not be loaded.')); });
    });
</script>
@endpush
