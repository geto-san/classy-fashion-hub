<x-shop::layouts>
    <x-slot:title>
        {{ __('classy-fashion::app.mobilemoney.title') }}
    </x-slot>

    <div class="mx-auto max-w-xl px-4 py-10 text-center">
        <h1 class="text-2xl font-semibold">
            {{ __('classy-fashion::app.mobilemoney.approve_title') }}
        </h1>

        <p class="mt-3 text-gray-600">
            {{ __('classy-fashion::app.mobilemoney.approve_body', ['amount' => core()->formatPrice($attempt->amount), 'network' => $attempt->network]) }}
        </p>

        <p class="mt-2 text-sm text-gray-500">
            {{ __('classy-fashion::app.mobilemoney.tx_ref', ['ref' => $attempt->tx_ref]) }}
        </p>

        <a
            href="{{ route('classy.mobilemoney.status', ['attempt' => $attempt->id]) }}"
            class="primary-button mt-6 inline-block rounded-2xl px-11 py-3"
        >
            {{ __('classy-fashion::app.mobilemoney.check_status') }}
        </a>
    </div>
</x-shop::layouts>
