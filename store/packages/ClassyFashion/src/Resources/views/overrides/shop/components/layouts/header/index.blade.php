{!! view_render_event('bagisto.shop.layout.header.before') !!}

<div class="cfh-announcement"><div class="cfh-announcement-side"><a href="{{ route('shop.home.contact_us') }}">{{ trans('classy-fashion::storefront.contact') }}</a></div><p>{{ trans('classy-fashion::storefront.promotion_title') }}</p><div class="cfh-announcement-side">{{ core()->getCurrentLocale()->name }} <span>{{ core()->getCurrentCurrencyCode() }}</span></div></div>

@if (core()->getCurrentChannel()->locales()->count() > 1 || core()->getCurrentChannel()->currencies()->count() > 1)
    <div class="cfh-locale-bar max-lg:hidden"><x-shop::layouts.header.desktop.top /></div>
@endif

<header class="cfh-header shadow-gray sticky top-0 z-10 bg-white shadow-sm max-lg:shadow-none">
    <v-header-switcher>

        <div class="flex flex-wrap max-lg:hidden">
            <div class="flex min-h-[68px] w-full justify-between border border-b border-l-0 border-r-0 border-t-0 px-[60px] max-1180:px-8">

                <div class="flex items-center gap-x-10 max-[1180px]:gap-x-5">

                    <span
                        class="shimmer block h-[29px] w-[131px] rounded"
                        role="presentation"
                    >
                    </span>


                    <div class="flex items-center gap-5">
                        <span
                            class="shimmer h-6 w-20 rounded"
                            role="presentation"
                        >
                        </span>

                        <span
                            class="shimmer h-6 w-20 rounded"
                            role="presentation"
                        >
                        </span>

                        <span
                            class="shimmer h-6 w-20 rounded"
                            role="presentation"
                        >
                        </span>
                    </div>
                </div>


                <div class="flex items-center gap-x-9 max-[1100px]:gap-x-6 max-lg:gap-x-8">

                    <div class="relative w-full max-w-[445px]">
                        <span
                            class="shimmer block h-[42px] w-[250px] rounded-lg px-11 py-3"
                            role="presentation"
                        >
                        </span>
                    </div>


                    <div class="mt-1.5 flex gap-x-8 max-[1100px]:gap-x-6 max-lg:gap-x-8">

                        <span
                            class="shimmer h-6 w-6 rounded"
                            role="presentation"
                        >
                        </span>


                        <span
                            class="shimmer h-6 w-6 rounded"
                            role="presentation"
                        >
                        </span>


                        <span
                            class="shimmer h-6 w-6 rounded"
                            role="presentation"
                        >
                        </span>
                    </div>
                </div>
            </div>
        </div>


        <div class="flex flex-wrap gap-4 px-4 pb-4 pt-6 shadow-sm lg:hidden">
            <div class="flex w-full items-center justify-between">

                <div class="flex items-center gap-x-1.5">

                    <span
                        class="shimmer block h-6 w-6 rounded"
                        role="presentation"
                    >
                    </span>


                    <span
                        class="shimmer block h-[29px] w-[131px] rounded"
                        role="presentation"
                    >
                    </span>
                </div>


                <div class="flex items-center gap-x-5 max-md:gap-x-4">

                    <span
                        class="shimmer block h-6 w-6 rounded"
                        role="presentation"
                    >
                    </span>


                    <span
                        class="shimmer block h-6 w-6 rounded"
                        role="presentation"
                    >
                    </span>


                    <span
                        class="shimmer block h-6 w-6 rounded"
                        role="presentation"
                    >
                    </span>
                </div>
            </div>


            <div class="flex w-full items-center">
                <div class="relative w-full">
                    <span
                        class="shimmer block h-[42px] w-full rounded-xl px-11 py-3.5 max-md:rounded-lg"
                        role="presentation"
                    >
                    </span>
                </div>
            </div>
        </div>
    </v-header-switcher>
</header>

{!! view_render_event('bagisto.shop.layout.header.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-header-switcher-template"
    >
        <v-desktop-header v-if="isDesktop"></v-desktop-header>

        <v-mobile-header v-else></v-mobile-header>
    </script>

    <script type="module">
        app.component('v-header-switcher', {
            template: '#v-header-switcher-template',

            data() {
                return {
                    isDesktop: window.innerWidth >= 1024
                }
            },

            mounted() {
                this.media = window.matchMedia('(min-width: 1024px)');

                this.media.addEventListener('change', this.handleMedia);
            },

            beforeUnmount() {
                this.media.removeEventListener('change', this.handleMedia);
            },

            methods: {
                handleMedia(e) {
                    this.isDesktop = e.matches;
                }
            }
        });

        app.component('v-desktop-header', {
            template: '#v-desktop-header-template'
        });

        app.component('v-mobile-header', {
            template: '#v-mobile-header-template'
        });
    </script>

    <script
        type="text/x-template"
        id="v-desktop-header-template"
    >
        <x-shop::layouts.header.desktop />
    </script>

    <script
        type="text/x-template"
        id="v-mobile-header-template"
    >
        <x-shop::layouts.header.mobile />
    </script>
@endPushOnce
