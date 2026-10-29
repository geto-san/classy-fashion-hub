<v-products-carousel
    src="{{ $src }}"
    title="{{ $title }}"
    eyebrow="{{ $eyebrow ?? '' }}"
    navigation-link="{{ $navigationLink ?? '' }}"
>
    <x-shop::shimmer.products.carousel :navigation-link="$navigationLink ?? false" />
</v-products-carousel>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-products-carousel-template"
    >
        <section
            class="cfh-section"
            v-if="! isLoading && products.length"
        >
            <header class="cfh-section__head">
                <div>
                    <p class="cfh-eyebrow" v-if="eyebrow" v-text="eyebrow"></p>

                    <h2 class="cfh-title" v-text="title"></h2>
                </div>

                <div class="cfh-section__nav">
                    <a
                        :href="navigationLink"
                        class="cfh-more"
                        v-if="navigationLink"
                    >
                        @lang('shop::app.components.products.carousel.view-all') +
                    </a>

                    <template v-if="products.length > 4">
                        <span
                            class="cfh-arrow icon-arrow-left-stylish rtl:icon-arrow-right-stylish"
                            role="button"
                            aria-label="@lang('shop::app.components.products.carousel.previous')"
                            tabindex="0"
                            @click="swipeLeft"
                        >
                        </span>

                        <span
                            class="cfh-arrow icon-arrow-right-stylish rtl:icon-arrow-left-stylish"
                            role="button"
                            aria-label="@lang('shop::app.components.products.carousel.next')"
                            tabindex="0"
                            @click="swipeRight"
                        >
                        </span>
                    </template>
                </div>
            </header>

            <div
                ref="swiperContainer"
                class="cfh-rail"
            >
                <x-shop::products.card
                    class="cfh-rail__item"
                    v-for="product in products"
                />
            </div>
        </section>

        <!-- Product Card Listing -->
        <template v-if="isLoading">
            <x-shop::shimmer.products.carousel :navigation-link="$navigationLink ?? false" />
        </template>
    </script>

    <script type="module">
        app.component('v-products-carousel', {
            template: '#v-products-carousel-template',

            props: [
                'src',
                'title',
                'eyebrow',
                'navigationLink',
            ],

            data() {
                return {
                    isLoading: true,

                    products: [],

                    offset: 280,

                    isScreenMax2xl: window.innerWidth <= 1440,
                };
            },

            mounted() {
                this.getProducts();
            },

            created() {
                window.addEventListener('resize', this.updateScreenSize);
            },

            beforeDestroy() {
                window.removeEventListener('resize', this.updateScreenSize);
            },

            methods: {
                getProducts() {
                    this.$axios.get(this.src)
                        .then(response => {
                            this.isLoading = false;

                            this.products = response.data.data;
                        }).catch(error => {
                            console.log(error);
                        });
                },

                updateScreenSize() {
                    this.isScreenMax2xl = window.innerWidth <= 1440;
                },

                swipeLeft() {
                    const container = this.$refs.swiperContainer;

                    container.scrollLeft -= this.offset;
                },

                swipeRight() {
                    const container = this.$refs.swiperContainer;

                    // Check if scroll reaches the end
                    if (container.scrollLeft + container.clientWidth >= container.scrollWidth) {
                        // Reset scroll to the beginning
                        container.scrollLeft = 0;
                    } else {
                        // Scroll to the right
                        container.scrollLeft += this.offset;
                    }
                },
            },
        });
    </script>
@endPushOnce
