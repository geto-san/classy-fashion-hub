<x-shop::layouts>
    <x-slot:title>
        {{ __('classy-fashion::app.mobilemoney.title') }}
    </x-slot>

    <div class="mx-auto max-w-xl px-4 py-10">
        <h1 class="text-2xl font-semibold">
            {{ __('classy-fashion::app.mobilemoney.title') }}
        </h1>

        <p class="mt-2 text-zinc-500">
            {{ __('classy-fashion::app.mobilemoney.choose_network', ['amount' => core()->formatPrice($cart->grand_total)]) }}
        </p>

        <form
            method="POST"
            action="{{ route('classy.mobilemoney.charge') }}"
            class="mt-6 flex flex-col gap-4"
        >
            @csrf

            <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">{{ __('classy-fashion::app.mobilemoney.network') }}</span>

                <select
                    name="network"
                    class="rounded-lg border px-4 py-3"
                    required
                >
                    @foreach ($networks as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">{{ __('classy-fashion::app.mobilemoney.phone') }}</span>

                <input
                    type="tel"
                    name="phone"
                    required
                    maxlength="16"
                    pattern="(\+?256|0)7[0-9]{8}"
                    inputmode="tel"
                    placeholder="0772000000"
                    class="rounded-lg border px-4 py-3"
                >
            </label>

            <button
                type="submit"
                class="primary-button rounded-2xl px-11 py-3"
            >
                {{ __('classy-fashion::app.mobilemoney.pay_now') }}
            </button>
        </form>
    </div>
</x-shop::layouts>
