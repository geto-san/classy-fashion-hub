<x-admin::layouts>
    <x-slot:title>
        {{ __('classy-fashion::app.audit.title') }}
    </x-slot>

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="pt-1.5 text-xl font-bold leading-6 text-gray-800 dark:text-white">
            {{ __('classy-fashion::app.audit.title') }}
        </p>

        <a
            href="{{ route('admin.classy.reports.audit', array_merge($filters, ['export' => 'csv'])) }}"
            class="secondary-button text-sm"
        >
            {{ __('classy-fashion::app.reports.export_csv') }}
        </a>
    </div>

    <form
        method="GET"
        action="{{ route('admin.classy.reports.audit') }}"
        class="mb-5 flex flex-wrap items-end gap-3"
    >
        <label class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300">
            {{ __('classy-fashion::app.audit.event') }}

            <select name="event" class="rounded-md border px-3 py-2">
                <option value="">{{ __('classy-fashion::app.audit.all') }}</option>

                @foreach ($events as $event)
                    <option value="{{ $event }}" @selected(($filters['event'] ?? null) === $event)>{{ $event }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300">
            {{ __('classy-fashion::app.audit.who') }}

            <select name="user" class="rounded-md border px-3 py-2">
                <option value="">{{ __('classy-fashion::app.audit.all') }}</option>

                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) ($filters['user'] ?? 0) === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300">
            {{ __('classy-fashion::app.reports.start') }}

            <input type="date" name="start" value="{{ $filters['start'] ?? '' }}" class="rounded-md border px-3 py-2">
        </label>

        <label class="flex flex-col gap-1 text-sm text-gray-600 dark:text-gray-300">
            {{ __('classy-fashion::app.reports.end') }}

            <input type="date" name="end" value="{{ $filters['end'] ?? '' }}" class="rounded-md border px-3 py-2">
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
                    <th class="px-4 py-3">{{ __('classy-fashion::app.audit.who') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.audit.event') }}</th>
                    <th class="px-4 py-3">{{ __('classy-fashion::app.audit.what') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($entries as $entry)
                    <tr class="border-b">
                        <td class="whitespace-nowrap px-4 py-3">{{ $entry->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $entry->causer?->name ?? __('classy-fashion::app.audit.system') }}</td>
                        <td class="px-4 py-3">{{ $entry->event }}</td>
                        <td class="px-4 py-3">{{ $entry->description }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">
                            {{ __('classy-fashion::app.audit.none') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $entries->links() }}
    </div>
</x-admin::layouts>
