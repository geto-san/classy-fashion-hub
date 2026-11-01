<div class="cfh-mobile-nav">
    <a class="cfh-mobile-logo" href="{{ route('shop.home.index') }}" aria-label="{{ config('app.name') }}"><img src="{{ core()->getCurrentChannel()->logo_url ?? asset('images/brand-logo.png') }}" width="126" height="29" alt="{{ config('app.name') }}"></a>
    <div class="cfh-mobile-utilities">
        <details class="cfh-header-search"><summary aria-label="{{ trans('classy-fashion::storefront.header_search') }}"><span class="icon-search" aria-hidden="true"></span></summary><div class="cfh-header-search-content"><form action="{{ route('shop.search.index') }}" role="search"><label class="sr-only" for="cfh-mobile-query">{{ trans('classy-fashion::storefront.header_search') }}</label><input id="cfh-mobile-query" type="search" name="query" value="{{ request('query') }}" placeholder="{{ trans('classy-fashion::storefront.header_search') }}" required><button type="submit" aria-label="{{ trans('classy-fashion::storefront.header_search') }}"><span class="icon-search" aria-hidden="true"></span></button></form></div></details>
        <a href="{{ route('shop.customers.account.profile.index') }}" aria-label="{{ trans('classy-fashion::storefront.header_account') }}"><span class="icon-users" aria-hidden="true"></span></a>
        @if (core()->getConfigData('sales.checkout.shopping_cart.cart_page'))
            @include('shop::checkout.cart.mini-cart')
        @endif
    </div>
</div>
@if (request()->routeIs('shop.home.index'))
    @include('shop::components.layouts.header.mobile.bottom')
@endif
@pushOnce('scripts')
    <script type="text/x-template" id="v-mobile-drawer-template">
            <x-shop::drawer
                position="left"
                width="100%"
                @close="onDrawerClose"
            >
                <x-slot:toggle>
                    <span class="text-2xl cursor-pointer icon-hamburger"></span>
                </x-slot>

                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <a href="{{ route('shop.home.index') }}">
                            <img
                                src="{{ core()->getCurrentChannel()->logo_url ?? bagisto_asset('images/logo.svg') }}"
                                alt="{{ core()->getCurrentChannel()->logo_alt ?: config('app.name') }}"
                                width="131"
                                height="29"
                            >
                        </a>
                    </div>
                </x-slot>

                <x-slot:content class="!p-0">

                    <div class="p-4 border-b border-zinc-200">
                        <div class="grid grid-cols-[auto_1fr] items-center gap-4 rounded-xl border border-zinc-200 p-2.5">
                            <div>
                                <img
                                src="{{ auth()->user()?->image_url ??  bagisto_asset('images/user-placeholder.png') }}"
                                    class="h-[60px] w-[60px] rounded-full max-md:rounded-full"
                                >
                            </div>

                            @guest('customer')
                                <a
                                    href="{{ route('shop.customer.session.create') }}"
                                    class="flex text-base font-medium"
                                >
                                    @lang('shop::app.components.layouts.header.mobile.login')

                                    <i class="icon-double-arrow text-2xl ltr:ml-2.5 rtl:mr-2.5"></i>
                                </a>
                            @endguest

                            @auth('customer')
                                <div
                                    class="flex flex-col justify-between gap-2.5 max-md:gap-0"
                                    v-pre
                                >
                                    <p class="text-2xl break-all font-mediums max-md:text-xl">Hello! {{ auth()->user()?->first_name }}</p>

                                    <p class="no-underline text-zinc-500 max-md:text-sm">{{ auth()->user()?->email }}</p>
                                </div>
                            @endauth
                        </div>
                    </div>

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.drawer.categories.before') !!}


                    <v-mobile-category ref="mobileCategory"></v-mobile-category>

                    {!! view_render_event('bagisto.shop.components.layouts.header.mobile.drawer.categories.after') !!}
                </x-slot>

                <x-slot:footer>

                @if(core()->getCurrentChannel()->locales()->count() > 1 || core()->getCurrentChannel()->currencies()->count() > 1 )
                                    <div class="fixed bottom-0 z-10 grid w-full max-w-full grid-cols-[1fr_auto_1fr] items-center justify-items-center border-t border-zinc-200 bg-white px-5 ltr:left-0 rtl:right-0">

                                        <x-shop::drawer
                                            position="bottom"
                                            width="100%"
                                        >

                                            <x-slot:toggle>
                                                <div
                                                    class="flex cursor-pointer items-center gap-x-2.5 px-2.5 py-3.5 text-lg font-medium uppercase max-md:py-3 max-sm:text-base"
                                                    role="button"
                                                    v-pre
                                                >
                                                    {{ core()->getCurrentCurrency()->symbol . ' ' . core()->getCurrentCurrencyCode() }}
                                                </div>
                                            </x-slot>


                                            <x-slot:header>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-lg font-semibold">
                                                        @lang('shop::app.components.layouts.header.mobile.currencies')
                                                    </p>
                                                </div>
                                            </x-slot>


                                            <x-slot:content class="!px-0">
                                                <div
                                                    class="overflow-auto"
                                                    :style="{ height: getCurrentScreenHeight }"
                                                >
                                                    <v-currency-switcher></v-currency-switcher>
                                                </div>
                                            </x-slot>
                                        </x-shop::drawer>


                                        <span class="h-5 w-0.5 bg-zinc-200"></span>


                                        <x-shop::drawer
                                            position="bottom"
                                            width="100%"
                                        >

                                            <x-slot:toggle>
                                                <div
                                                    class="flex cursor-pointer items-center gap-x-2.5 px-2.5 py-3.5 text-lg font-medium uppercase max-md:py-3 max-sm:text-base"
                                                    role="button"
                                                    v-pre
                                                >
                                                    <img
                                        src="{{ ! empty(core()->getCurrentLocale()->logo_url)
                        ? core()->getCurrentLocale()->logo_url
                        : bagisto_asset('images/default-language.svg')
                                                            }}"
                                                        class="h-full"
                                                        alt="Default locale"
                                                        width="24"
                                                        height="16"
                                                    />

                                                    {{ core()->getCurrentChannel()->locales()->orderBy('name')->where('code', app()->getLocale())->value('name') }}
                                                </div>
                                            </x-slot>


                                            <x-slot:header>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-lg font-semibold">
                                                        @lang('shop::app.components.layouts.header.mobile.locales')
                                                    </p>
                                                </div>
                                            </x-slot>


                                            <x-slot:content class="!px-0">
                                                <div
                                                    class="overflow-auto"
                                                    :style="{ height: getCurrentScreenHeight }"
                                                >
                                                    <v-locale-switcher></v-locale-switcher>
                                                </div>
                                            </x-slot>
                                        </x-shop::drawer>
                                    </div>
                    @endif
                </x-slot>
            </x-shop::drawer>
        </script>

    <script
        type="text/x-template"
        id="v-mobile-category-template"
    >

    <div class="relative h-full overflow-hidden">

            <div
                class="flex h-full transition-transform duration-300"
                :class="{
                        'ltr:translate-x-0 rtl:translate-x-0': currentViewLevel !== 'third',
                        'ltr:-translate-x-full rtl:translate-x-full': currentViewLevel === 'third'
                }"
            >

            <div class="flex-shrink-0 w-full h-full px-6 overflow-auto">
                <div class="py-4">
                        <div
                            v-for="category in categories"
                            :key="category.id"
                            :class="{'mb-2': category.children && category.children.length}"
                        >
                        <div class="flex items-center justify-between py-2 transition-colors duration-200 cursor-pointer">
                            <a :href="category.url" class="text-base font-medium text-black">
                                @{{ category.name }}
                            </a>
                        </div>


                            <div v-if="category.children && category.children.length" >
                                <div
                                    v-for="secondLevelCategory in category.children"
                                    :key="secondLevelCategory.id"
                                >
                                    <div
                                        class="flex items-center justify-between py-2 transition-colors duration-200 cursor-pointer"
                                        @click="showThirdLevel(secondLevelCategory, category, $event)"
                                    >
                                    <a :href="secondLevelCategory.url" class="text-sm font-normal">
                                        @{{ secondLevelCategory.name }}
                                    </a>

                                        <span
                                            v-if="secondLevelCategory.children && secondLevelCategory.children.length"
                                            class="icon-arrow-right rtl:icon-arrow-left"
                                        ></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


                <div
                    class="flex-shrink-0 w-full h-full"
                    v-if="currentViewLevel === 'third'"
                >
                <div class="px-6 py-4 border-b border-gray-200">
                        <button
                            @click="goBackToMainView"
                            class="flex items-center justify-center gap-2 focus:outline-none"
                            aria-label="Go back"
                        >
                        <span class="text-lg icon-arrow-left rtl:icon-arrow-right"></span>
                        <div class="text-base font-medium text-black">
                            @lang('shop::app.components.layouts.header.mobile.back-button')
                        </div>
                    </button>
                </div>


                <div class="px-6 py-4">
                        <div
                            v-for="thirdLevelCategory in currentSecondLevelCategory?.children"
                            :key="thirdLevelCategory.id"
                            class="mb-2"
                        >
                            <a
                                :href="thirdLevelCategory.url"
                                class="block py-2 text-sm transition-colors duration-200"
                            >
                            @{{ thirdLevelCategory.name }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </script>

    <script type="module">
        app.component('v-mobile-category', {
            template: '#v-mobile-category-template',

            data() {
                return  {
                    categories: [],
                    currentViewLevel: 'main',
                    currentSecondLevelCategory: null,
                    currentParentCategory: null
                }
            },

            mounted() {
                this.initCategories();
            },

            computed: {
                getCurrentScreenHeight() {
                    return window.innerHeight - (window.innerWidth < 920 ? 61 : 0) + 'px';
                },
            },

            methods: {
                initCategories() {
                    try {
                        const stored = localStorage.getItem('categories');

                        if (stored) {
                            this.categories = JSON.parse(stored);
                            this.isLoading = false;
                            return;
                        }

                    } catch (e) {}

                    this.getCategories();
                },
                getCategories() {
                    this.$axios.get("{{ route('shop.api.categories.tree') }}")
                        .then(response => {
                            this.categories = response.data.data;
                            localStorage.setItem('categories', JSON.stringify(this.categories));
                        })
                        .catch(error => {
                            console.log(error);
                        });
                },

                showThirdLevel(secondLevelCategory, parentCategory, event) {
                    if (secondLevelCategory.children && secondLevelCategory.children.length) {
                        this.currentSecondLevelCategory = secondLevelCategory;
                        this.currentParentCategory = parentCategory;
                        this.currentViewLevel = 'third';

                        if (event) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                    }
                },

                goBackToMainView() {
                    this.currentViewLevel = 'main';
                }
            },
        });

        app.component('v-mobile-drawer', {
            template: '#v-mobile-drawer-template',

            methods: {
                onDrawerClose() {
                    this.$refs.mobileCategory.currentViewLevel = 'main';
                }
            },
        });
    </script>
@endPushOnce
