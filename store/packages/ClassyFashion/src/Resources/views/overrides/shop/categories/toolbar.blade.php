{!! view_render_event('bagisto.shop.categories.view.toolbar.before') !!}

<v-toolbar @filter-applied='setFilters("toolbar", $event)'></v-toolbar>

{!! view_render_event('bagisto.shop.categories.view.toolbar.after') !!}

@inject('toolbar' , 'Webkul\Product\Helpers\Toolbar')

@pushOnce('scripts')
    <script
        type="text/x-template"
        id='v-toolbar-template'
    >
        <div>

            <div class="cfh-catalog-toolbar flex justify-between max-md:hidden">
                {!! view_render_event('bagisto.shop.categories.toolbar.filter.before') !!}


                <span class="cfh-sort-label">{{ trans('shop::app.products.sort-by.title') }}</span>
                <x-shop::dropdown
                    class="z-[1]"
                    position="bottom-left"
                >
                    <x-slot:toggle>

                        <button class="flex w-full max-w-[200px] cursor-pointer items-center justify-between gap-4 rounded-lg border border-zinc-200 bg-white p-3.5 text-base transition-all hover:border-gray-400 focus:border-gray-400 max-md:w-[110px] max-md:border-0 max-md:pl-2.5 max-md:pr-2.5">
                            @{{ sortLabel ?? "@lang('shop::app.products.sort-by.title')" }}

                            <span
                                class="icon-arrow-down text-2xl"
                                role="presentation"
                            ></span>
                        </button>
                    </x-slot>


                    <x-slot:menu>
                        <x-shop::dropdown.menu.item
                            v-for="(sort, key) in filters.available.sort"
                            ::class="{'bg-gray-100': sort.value == filters.applied.sort}"
                            @click="apply('sort', sort.value)"
                        >
                            @{{ sort.title }}
                        </x-shop::dropdown.menu.item>
                    </x-slot>
                </x-shop::dropdown>

                {!! view_render_event('bagisto.shop.categories.toolbar.filter.after') !!}

                {!! view_render_event('bagisto.shop.categories.toolbar.pagination.before') !!}


                <div class="cfh-mode-switch flex items-center gap-4">
                    <span>{{ trans('shop::app.categories.toolbar.grid') }}</span>
                    <div class="flex items-center gap-5">
                        <span
                            role="button"
                            aria-label="@lang('shop::app.categories.toolbar.list')"
                            tabindex="0"
                            class="cfh-grid-choice cfh-grid-four" :class="{'cfh-grid-selected': filters.applied.mode === 'list'}"
                            @click="changeMode('list')"
                        >
                        </span>

                        <span
                            role="button"
                            aria-label="@lang('shop::app.categories.toolbar.grid')"
                            tabindex="0"
                            class="cfh-grid-choice cfh-grid-three" :class="{'cfh-grid-selected': filters.applied.mode === 'grid'}"
                            @click="changeMode('grid')"
                        >
                        </span>
                    </div>
                </div>

                {!! view_render_event('bagisto.shop.categories.toolbar.pagination.after') !!}
            </div>


            <div class="md:hidden">
                <ul>
                    <li
                        class="px-4 py-2.5"
                        :class="{'bg-gray-100': sort.value == filters.applied.sort}"
                        v-for="(sort, key) in filters.available.sort"
                        @click="apply('sort', sort.value)"
                    >
                        @{{ sort.title }}
                    </li>
                </ul>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-toolbar', {
            template: '#v-toolbar-template',

            data() {
                return {
                    filters: {
                        available: {
                            sort: @json($toolbar->getAvailableOrders()),

                            limit: @json($toolbar->getAvailableLimits()),

                            mode: @json($toolbar->getAvailableModes()),
                        },

                        default: {
                            sort: '{{ $toolbar->getOrder([])['value'] }}',

                            limit: '{{ $toolbar->getLimit([]) }}',

                            mode: '{{ $toolbar->getMode([]) }}',
                        },

                        applied: {
                            sort: '{{ $toolbar->getOrder($params ?? [])['value'] }}',

                            limit: '{{ $toolbar->getLimit($params ?? []) }}',

                            mode: '{{ $toolbar->getMode($params ?? []) }}',
                        }
                    }
                };
            },

            created() {
                let queryParams = new URLSearchParams(window.location.search);

                queryParams.forEach((value, filter) => {
                    if (this.isAvailable(filter, value)) {
                        this.filters.applied[filter] = value;
                    }
                });
            },

            mounted() {
                this.setFilters();
            },

            computed: {
                sortLabel() {
                    return this.filters.available.sort.find(sort => sort.value === this.filters.applied.sort).title;
                }
            },

            methods: {
                isAvailable(filter, value) {
                    if (! ['sort', 'limit', 'mode'].includes(filter)) {
                        return false;
                    }

                    if (filter === 'sort') {
                        return this.filters.available.sort.some((sort) => sort.value === value);
                    }

                    return this.filters.available[filter].some((available) => String(available) === value);
                },

                apply(type, value) {
                    this.filters.applied[type] = value;

                    this.setFilters();
                },

                changeMode(value = 'grid') {
                    this.filters.applied['mode'] = value;

                    this.setFilters();
                },

                setFilters() {
                    let filters = {};

                    for (let key in this.filters.applied) {
                        if (this.filters.applied[key] != this.filters.default[key]) {
                            filters[key] = this.filters.applied[key];
                        }
                    }

                    this.$emit('filter-applied', {
                        default: this.filters.default,
                        applied: filters,
                    });
                }
            },
        });
    </script>
@endPushOnce
