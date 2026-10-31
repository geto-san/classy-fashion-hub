<x-admin::layouts>
    <x-slot:title>{{ __('classy-fashion::app.stock.title') }}</x-slot>
    <h1 class="mb-5 text-xl font-bold">{{ __('classy-fashion::app.stock.title') }}</h1>
    @if ($errors->any())
        <div role="alert" class="mb-4 text-red-600">{{ $errors->first() }}</div>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead><tr>
                <th class="p-3">SKU</th>
                <th class="p-3">{{ __('classy-fashion::app.stock.source') }}</th>
                <th class="p-3">{{ __('classy-fashion::app.stock.quantity') }}</th>
            </tr></thead>
            <tbody>
                @foreach ($inventories as $inventory)
                    <tr class="border-b">
                        <td class="p-3">{{ $inventory->product?->sku }}</td>
                        <td class="p-3">{{ $inventory->inventory_source_id }}</td>
                        <td class="p-3">
                            <form method="POST" action="{{ route('admin.classy.stock.update', $inventory->id) }}" class="flex gap-3">
                                @csrf
                                @method('PUT')
                                <label for="qty-{{ $inventory->id }}" class="sr-only">{{ __('classy-fashion::app.stock.quantity') }} {{ $inventory->product?->sku }}</label>
                                <input id="qty-{{ $inventory->id }}" name="qty" type="number" min="0" step="1" required value="{{ $inventory->qty }}" class="w-28 rounded border px-3 py-2">
                                <button class="primary-button">{{ __('classy-fashion::app.stock.save') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $inventories->links() }}
</x-admin::layouts>
