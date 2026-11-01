@props([
    'isActive' => false,
    'position' => 'right',
    'width'    => '500px',
])

<v-drawer
    {{ $attributes }}
    is-active="{{ $isActive }}"
    position="{{ $position }}"
    width="{{ $width }}"
>
    @isset($toggle)
        <template v-slot:toggle>
            {{ $toggle }}
        </template>
    @endisset

    @isset($header)
        <template v-slot:header="{ close }">
            <div {{ $header->attributes->merge(['class' => 'grid gap-y-2.5 p-6 pb-5 max-md:gap-y-1.5 max-md:border-b max-md:border-zinc-200 max-md:p-4 max-md:gap-y-1 max-md:font-semibold']) }}>
                {{ $header }}

                <div class="absolute top-5 max-sm:top-4 ltr:right-5 rtl:left-5">
                    <button type="button" class="icon-cancel cfh-panel-close" aria-label="{{ trans('classy-fashion::storefront.close_panel') }}" @click="close"></button>
                </div>
            </div>
        </template>
    @endisset

    @isset($content)
        <template v-slot:content>
            <div {{ $content->attributes->merge(['class' => 'flex-1 overflow-auto px-6 max-md:px-4']) }}>
                {{ $content }}
            </div>
        </template>
    @endisset

    @isset($footer)
        <template v-slot:footer>
            <div {{ $footer->attributes->merge(['class' => 'pb-8 max-md:pb-2']) }}>
                {{ $footer }}
            </div>
        </template>
    @endisset
</v-drawer>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-drawer-template"
    >
        <div>

            <div @click="open">
                <slot name="toggle"></slot>
            </div>


            <transition
                tag="div"
                name="drawer-overlay"
                enter-class="duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-class="duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    class="fixed inset-0 z-20 bg-gray-500 bg-opacity-50 transition-opacity"
                    @click="close"
                    aria-hidden="true"
                    v-show="isOpen"
                ></div>
            </transition>


            <transition
                tag="div"
                name="drawer"
                :enter-from-class="enterFromLeaveToClasses"
                enter-active-class="transform transition duration-200 ease-in-out"
                enter-to-class="translate-x-0"
                leave-from-class="translate-x-0"
                leave-active-class="transform transition duration-200 ease-in-out"
                :leave-to-class="enterFromLeaveToClasses"
            >
                <div
                    class="fixed z-[1000] overflow-hidden bg-white max-md:!w-full"
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ trans('classy-fashion::storefront.shopping_panel') }}"
                    tabindex="-1"
                    @keydown="handlePanelKeydown($event)"
                    :class="{
                        'inset-x-0 top-0': position == 'top',
                        'inset-x-0 bottom-0 max-sm:max-h-full': position == 'bottom',
                        'inset-y-0 ltr:right-0 rtl:left-0': position == 'right',
                        'inset-y-0 ltr:left-0 rtl:right-0': position == 'left'
                    }"
                    :style="'width:' + width"
                    v-show="isOpen"
                >
                    <div class="pointer-events-auto h-full w-full overflow-auto bg-white">
                        <div class="flex h-full w-full flex-col">
                            <div class="min-h-0 min-w-0 flex-1 overflow-auto">
                                <div class="flex h-full flex-col">
                                    <slot
                                        name="header"
                                        :close="close"
                                    >
                                        Default Header
                                    </slot>


                                    <slot name="content"></slot>


                                    <slot name="footer"></slot>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </script>

    <script type="module">
        app.component('v-drawer', {
            template: '#v-drawer-template',

            props: [
                'isActive',
                'position',
                'width'
            ],

            data() {
                return {
                    isOpen: this.isActive,
                };
            },

            watch: {
                isOpen(value) {
                    if (value) {
                        this.previousFocus = document.activeElement;
                        document.body.style.overflow = 'hidden';
                        this.$nextTick(() => this.$refs.panel.focus());
                    } else {
                        document.body.style.overflow = 'auto';
                        document.body.style.paddingRight = '';
                        this.previousFocus?.focus();
                    }
                },

                isActive: function(newVal, oldVal) {
                    this.isOpen = newVal;
                }
            },

            computed: {
                enterFromLeaveToClasses() {
                    if (this.position == 'top') {
                        return '-translate-y-full';
                    } else if (this.position == 'bottom') {
                        return 'translate-y-full';
                    } else if (this.position == 'left') {
                        return 'ltr:-translate-x-full rtl:translate-x-full';
                    } else if (this.position == 'right') {
                        return 'ltr:translate-x-full rtl:-translate-x-full';
                    }
                }
            },

            methods: {
                handlePanelKeydown(event) {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        event.stopPropagation();
                        this.close();
                        return;
                    }

                    if (event.key !== 'Tab') {
                        return;
                    }

                    const elements = [...this.$refs.panel.querySelectorAll('button:not([disabled]), a[href], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]')]
                        .filter(element => element.getClientRects().length);
                    const first = elements[0];
                    const last = elements[elements.length - 1];

                    if (! first) {
                        event.preventDefault();
                    } else if (event.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.panel)) {
                        event.preventDefault();
                        last.focus();
                    } else if (! event.shiftKey && (document.activeElement === last || document.activeElement === this.$refs.panel)) {
                        event.preventDefault();
                        first.focus();
                    }
                },

                toggle() {
                    this.isOpen = ! this.isOpen;

                    if (this.isOpen) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow ='auto';
                    }

                    document.body.style.paddingRight = '';

                    this.$emit('toggle', { isActive: this.isOpen });
                },

                open() {
                    this.isOpen = true;

                    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;

                    document.body.style.overflow = 'hidden';

                    document.body.style.paddingRight = `${scrollbarWidth}px`;

                    this.$emit('open', { isActive: this.isOpen });
                },

                close() {
                    this.isOpen = false;

                    document.body.style.overflow = 'auto';

                    document.body.style.paddingRight = '';

                    this.$emit('close', { isActive: this.isOpen });
                }
            },
        });
    </script>
@endPushOnce
