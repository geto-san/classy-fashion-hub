<x-shop::layouts>
    <x-slot:title>
        {{ __('classy-fashion::app.mobilemoney.title') }}
    </x-slot>

    <div class="mx-auto max-w-xl px-4 py-10 text-center">
        <h1 class="text-2xl font-semibold">
            {{ $attempt->provider === 'coursework' ? trans('classy-fashion::coursework.payment_title') : trans('classy-fashion::app.mobilemoney.approve_title') }}
        </h1>

        <p class="mt-3 text-zinc-500">
            {{ $attempt->provider === 'coursework' ? trans('classy-fashion::coursework.payment_body', ['amount' => core()->formatPrice($attempt->amount)]) : trans('classy-fashion::app.mobilemoney.approve_body', ['amount' => core()->formatPrice($attempt->amount), 'network' => $attempt->network]) }}
        </p>

        @if (config('classy.coursework_mode') && $attempt->provider === 'coursework')
            <form method="POST" action="{{ route('classy.mobilemoney.practice', ['attempt' => $attempt->public_id]) }}" class="mt-6 flex flex-wrap justify-center gap-3">
                @csrf
                <button type="submit" name="result" value="success" class="primary-button rounded px-6 py-3">{{ trans('classy-fashion::coursework.approve_payment') }}</button>
                <button type="submit" name="result" value="failed" class="secondary-button rounded px-6 py-3">{{ trans('classy-fashion::coursework.decline_payment') }}</button>
            </form>
        @endif

        <p class="mt-2 text-sm text-zinc-500">
            {{ __('classy-fashion::app.mobilemoney.tx_ref', ['ref' => $attempt->tx_ref]) }}
        </p>

        @if (! empty($till))
            <div class="mt-6 rounded-lg border border-zinc-200 bg-white p-4 text-left">
                <h2 class="font-semibold">
                    {{ __('classy-fashion::app.mobilemoney.till_title') }}
                </h2>

                <p class="mt-1 text-sm text-zinc-500">
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
