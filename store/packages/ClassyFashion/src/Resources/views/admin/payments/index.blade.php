<x-admin::layouts>
    <x-slot:title>
        {{ __('classy-fashion::app.payments.title') }}
    </x-slot>

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="pt-1.5 text-xl font-bold leading-6 text-gray-800 dark:text-white">
            {{ __('classy-fashion::app.payments.title') }}
        </p>
    </div>

    @if ($attention > 0)
        <div class="mb-5 rounded border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            {{ __('classy-fashion::app.payments.attention', ['count' => $attention]) }}
        </div>
    @endif

    <form
        method="GET"
        action="{{ route('admin.classy.payments.index') }}"
        class="mb-5 flex flex-wrap items-end gap-3"
    >
        <label class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300">
            {{ __('classy-fashion::app.payments.status') }}

            <select name="status" class="rounded-md border px-3 py-2">
                <option value="">{{ __('classy-fashion::app.audit.all') }}</option>

                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="primary-button px-6 py-2 text-sm">
            {{ __('classy-fashion::app.reports.apply') }}
        </button>
    </form>

    <div class="overflow-x-auto rounded bg-white shadow-sm dark:bg-gray-900">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="px-4 py-3">{{ __('classy-fashion::app.reports.date') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.payments.reference') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.mobilemoney.network') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.payments.amount') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.payments.status') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.payments.order') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.payments.note') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($attempts as $attempt)
                    <tr class="border-b {{ $attempt->needsAttention() ? 'bg-red-50' : '' }}">
                        <td class="whitespace-nowrap px-4 py-3">{{ $attempt->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $attempt->tx_ref }}</td>
                        <td class="px-4 py-3">{{ $attempt->network }}</td>
                        <td class="px-4 py-3">{{ core()->formatBasePrice($attempt->amount) }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $attempt->status }}</td>
                        <td class="px-4 py-3">
                            @if ($attempt->order_id)
                                <a class="text-blue-600" href="{{ route('admin.sales.orders.view', $attempt->order_id) }}">#{{ $attempt->order_id }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $attempt->failure_reason }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                            {{ __('classy-fashion::app.payments.none') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $attempts->links() }}
    </div>
</x-admin::layouts>
