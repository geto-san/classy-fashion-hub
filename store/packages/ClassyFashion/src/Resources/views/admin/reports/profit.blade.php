<x-admin::layouts>
    <x-slot:title>
        {{ __('classy-fashion::app.reports.profit_title') }}
    </x-slot>

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="pt-1.5 text-xl font-bold leading-6 text-gray-800 dark:text-white">
            {{ __('classy-fashion::app.reports.profit_title') }}
        </p>

        <a
            href="{{ route('admin.classy.reports.profit', ['start' => $start, 'end' => $end, 'export' => 'csv']) }}"
            class="secondary-button text-sm"
        >
            {{ __('classy-fashion::app.reports.export_csv') }}
        </a>
    </div>

    <form
        method="GET"
        action="{{ route('admin.classy.reports.profit') }}"
        class="mb-5 flex flex-wrap items-end gap-3"
    >
        <label class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300">
            {{ __('classy-fashion::app.reports.start') }}

            <input
                type="date"
                name="start"
                value="{{ $start }}"
                class="rounded-md border px-3 py-2"
            >
        </label>

        <label class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300">
            {{ __('classy-fashion::app.reports.end') }}

            <input
                type="date"
                name="end"
                value="{{ $end }}"
                class="rounded-md border px-3 py-2"
            >
        </label>

        <button
            type="submit"
            class="primary-button px-6 py-2 text-sm"
        >
            {{ __('classy-fashion::app.reports.apply') }}
        </button>
    </form>

    <div class="mb-5 grid grid-cols-3 gap-4 max-sm:grid-cols-1">
        <div class="rounded bg-white p-4 shadow-sm dark:bg-gray-900">
            <p class="text-sm text-gray-500">{{ __('classy-fashion::app.reports.total_sales') }}</p>
            <p class="text-xl font-bold">{{ core()->formatBasePrice($totals['sales']) }}</p>
        </div>

        <div class="rounded bg-white p-4 shadow-sm dark:bg-gray-900">
            <p class="text-sm text-gray-500">{{ __('classy-fashion::app.reports.transactions') }}</p>
            <p class="text-xl font-bold">{{ $totals['transactions'] }}</p>
        </div>

        <div class="rounded bg-white p-4 shadow-sm dark:bg-gray-900">
            <p class="text-sm text-gray-500">{{ __('classy-fashion::app.reports.total_profit') }}</p>
            <p class="text-xl font-bold text-emerald-600">{{ core()->formatBasePrice($totals['profit']) }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded bg-white shadow-sm dark:bg-gray-900">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="px-4 py-3">{{ __('classy-fashion::app.reports.date') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.reports.transactions') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.reports.total_sales') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.reports.total_profit') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b">
                        <td class="px-4 py-3">{{ $row['date'] }}</td>
                        <td class="px-4 py-3">{{ $row['transactions'] }}</td>
                        <td class="px-4 py-3">{{ core()->formatBasePrice($row['sales']) }}</td>
                        <td class="px-4 py-3 font-semibold text-emerald-600">{{ core()->formatBasePrice($row['profit']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="4"
                            class="px-4 py-6 text-center text-gray-500"
                        >
                            {{ __('classy-fashion::app.reports.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts>
