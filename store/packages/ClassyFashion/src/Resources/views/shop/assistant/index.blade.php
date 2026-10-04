<x-shop::layouts>
    <x-slot:title>
        {{ __('classy-fashion::app.assistant.title') }}
    </x-slot>

    <div class="mx-auto max-w-2xl px-4 py-10">
        <p class="text-sm text-zinc-500">
            Classy Fashion Hub
        </p>

        <h1 class="mt-1 font-dmserif text-4xl max-sm:text-3xl">
            {{ __('classy-fashion::app.assistant.title') }}
        </h1>

        <p class="mt-2 text-zinc-500">
            {{ __('classy-fashion::app.assistant.subtitle') }}
        </p>

        <div
            id="cfh-chat"
            class="mt-6 flex h-[420px] flex-col gap-3 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-4"
        >
            <div class="max-w-[85%] rounded-lg bg-white p-3 text-sm shadow-sm">
                {{ __('classy-fashion::app.assistant.greeting') }}
            </div>
        </div>

        <form
            id="cfh-chat-form"
            class="mt-4 flex gap-2"
        >
            <input
                id="cfh-chat-input"
                type="text"
                maxlength="500"
                required
                autocomplete="off"
                placeholder="{{ __('classy-fashion::app.assistant.placeholder') }}"
                class="w-full rounded border px-4 py-3"
            >

            <button
                type="submit"
                class="primary-button shrink-0 rounded px-6"
            >
                {{ __('classy-fashion::app.assistant.send') }}
            </button>
        </form>
    </div>

    <script>
        (function () {
            const box = document.getElementById('cfh-chat');
            const form = document.getElementById('cfh-chat-form');
            const input = document.getElementById('cfh-chat-input');
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

            function bubble(text, mine) {
                const div = document.createElement('div');
                div.className = mine
                    ? 'max-w-[85%] self-end rounded-lg bg-navyBlue p-3 text-sm text-white'
                    : 'max-w-[85%] rounded-lg bg-white p-3 text-sm shadow-sm';
                div.textContent = text;
                box.appendChild(div);
                box.scrollTop = box.scrollHeight;
                return div;
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const message = input.value.trim();

                if (! message) {
                    return;
                }

                bubble(message, true);
                input.value = '';

                const waiting = bubble('…', false);

                fetch("{{ route('classy.assistant.chat') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ message: message }),
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));

                        waiting.remove();

                        bubble(data.reply || 'Sorry — please try again.', false);

                        (data.products || []).forEach((p) => {
                            const div = document.createElement('div');
                            div.className = 'max-w-[85%] rounded-lg bg-zinc-100 p-3 text-sm';

                            const a = document.createElement('a');
                            a.href = p.url;
                            a.className = 'underline';
                            a.style.fontWeight = '600';
                            a.textContent = p.name + ' — ' + p.price;

                            div.appendChild(a);
                            box.appendChild(div);
                            box.scrollTop = box.scrollHeight;
                        });
                    })
                    .catch(() => {
                        waiting.remove();
                        bubble('Network trouble — please try again.', false);
                    });
            });
        })();
    </script>
</x-shop::layouts>
