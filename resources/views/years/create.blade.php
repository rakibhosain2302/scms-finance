@extends(config('scms-finance.layout', 'nit::master'))
@section('title', __('Open a financial year'))
@section('nx-inline-errors', '1')

@section('content')
<div class="nx-page">
    @include('finance::partials.alerts')
    <form method="POST" action="{{ route('finance.financial-years.store') }}" data-nx-once novalidate>
        @csrf
        <x-nit::card :title="__('Open a financial year')" :subtitle="__('Years may not overlap.')">
            <div class="nx-form-grid">
                <x-nit::input name="name" :label="__('Name')" :value="$starts->year.'-'.substr((string) ($starts->year + 1), 2)" required maxlength="50" wrapper-class="nx-full" />
                <x-nit::input name="starts_on" type="date" :label="__('Starts')" :value="$starts->toDateString()" required />
                <x-nit::input name="ends_on" type="date" :label="__('Ends')" :value="$ends->toDateString()" required />
            </div>
            <x-slot:footer>
                <x-nit::button variant="light" :href="route('finance.financial-years.index')">{{ __('Cancel') }}</x-nit::button>
                <x-nit::button icon="fas fa-check">{{ __('Open year') }}</x-nit::button>
            </x-slot:footer>
        </x-nit::card>
    </form>
</div>
@endsection
