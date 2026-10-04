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

        @if (! empty($till))
            <div class="mt-6 rounded-lg border border-[#E8E0D4] bg-white p-4 text-left">
                <h2 class="font-semibold">
                    {{ __('classy-fashion::app.mobilemoney.till_title') }}
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    {{ __('classy-fashion::app.mobilemoney.till_body', ['amount' => core()->formatPrice($attempt->amount), 'till' => $till, 'network' => $attempt->network]) }}
                </p>

                @if (empty($attempt->customer_claim))
                    <form
                        method="POST"
                        action="{{ route('classy.mobilemoney.claim', ['attempt' => $attempt->public_id]) }}"
                        class="mt-3 flex gap-2"
                    >
                        @csrf

                        <input
                            type="text"
                            name="customer_claim"
                            required
                            maxlength="100"
                            placeholder="{{ __('classy-fashion::app.mobilemoney.till_ref') }}"
                            class="w-full rounded border px-4 py-3"
                        >

                        <button
                            type="submit"
                            class="primary-button shrink-0 rounded px-6"
                        >
                            {{ __('classy-fashion::app.mobilemoney.till_save') }}
                        </button>
                    </form>
                @else
                    <p class="mt-3 text-sm font-semibold text-emerald-700">
                        {{ __('classy-fashion::app.mobilemoney.claim_saved') }}
                    </p>
                @endif
            </div>
        @endif

        <a
            href="{{ route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]) }}"
            class="primary-button mt-6 inline-block rounded-2xl px-11 py-3"
        >
            {{ __('classy-fashion::app.mobilemoney.check_status') }}
        </a>
    </div>
</x-shop::layouts>
