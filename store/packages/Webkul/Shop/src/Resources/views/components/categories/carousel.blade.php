<v-categories-carousel
    src="{{ $src }}"
    title="{{ $title }}"
    navigation-link="{{ $navigationLink ?? '' }}"
>
    <x-shop::shimmer.categories.carousel
        :count="8"
        :navigation-link="$navigationLink ?? false"
    />
</v-categories-carousel>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-categories-carousel-template"
    >
        <div
            class="container mt-14 max-lg:px-8 max-md:mt-7 max-md:!px-0 max-sm:mt-5"
            v-if="! isLoading && categories?.length"
        >
            <div class="mb-6 flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#B98A1F]">
                        Classy Fashion Hub
                    </p>

                    <h2
                        class="mt-1 text-2xl font-bold text-gray-900 max-sm:text-xl"
                        v-text="title || 'Shop by Category'"
                    >
                    </h2>
                </div>
            </div>

            <div class="relative">
                <div
                    ref="swiperContainer"
                    class="scrollbar-hide flex gap-6 overflow-auto scroll-smooth pb-2 max-lg:gap-4"
                >
                    <div
                        class="group relative min-w-[260px] max-w-[260px] overflow-hidden rounded-2xl bg-zinc-100 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl max-md:min-w-[200px] max-md:max-w-[200px]"
                        v-for="category in categories"
                    >
                        <a
                            :href="category.slug"
                            class="block"
                            :aria-label="category.name"
                        >
                            <div class="h-[320px] w-full overflow-hidden max-md:h-[240px]">
                                <x-shop::media.images.lazy
                                    ::src="category.logo?.large_image_url || fallback"
                                    ::srcset="`
                                        ${(category.logo?.medium_image_url || fallback)} 300w,
                                        ${(category.logo?.large_image_url || fallback)} 600w
                                    `"
                                    sizes="(max-width: 768px) 200px, 260px"
                                    width="260"
                                    height="320"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                    ::alt="category.name"
                                />
                            </div>

                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#4A1942] via-[#4A1942]/70 to-transparent p-4 pt-12">
                                <p
                                    class="text-lg font-semibold text-white"
                                    v-text="category.name"
                                >
                                </p>

                                <p class="mt-0.5 text-sm font-medium text-[#E8B84B]">
                                    Shop now &rarr;
                                </p>
                            </div>
                        </a>
                    </div>
                </div>

                <span
                    class="icon-arrow-left-stylish absolute -left-10 top-1/2 flex h-[50px] w-[50px] -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-black bg-white text-2xl transition hover:bg-black hover:text-white max-lg:-left-7 max-md:hidden"
                    role="button"
                    aria-label="@lang('shop::components.carousel.previous')"
                    tabindex="0"
                    @click="swipeLeft"
                >
                </span>

                <span
                    class="icon-arrow-right-stylish absolute -right-6 top-1/2 flex h-[50px] w-[50px] -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-black bg-white text-2xl transition hover:bg-black hover:text-white max-lg:-right-7 max-md:hidden"
                    role="button"
                    aria-label="@lang('shop::components.carousel.next')"
                    tabindex="0"
                    @click="swipeRight"
                >
                </span>
            </div>
        </div>

        <!-- Category Carousel Shimmer -->
        <template v-if="isLoading">
            <x-shop::shimmer.categories.carousel
                :count="8"
                :navigation-link="$navigationLink ?? false"
            />
        </template>
    </script>

    <script type="module">
        app.component('v-categories-carousel', {
            template: '#v-categories-carousel-template',

            props: [
                'src',
                'title',
                'navigationLink',
            ],

            data() {
                return {
                    isLoading: true,

                    categories: [],

                    offset: 323,

                    fallback: "{{ product_image()->getPlaceholderUrl('small') }}"
                };
            },

            mounted() {
                this.getCategories();
            },

            methods: {
                getCategories() {
                    this.$axios.get(this.src)
                        .then(response => {
                            this.isLoading = false;

                            this.categories = response.data.data;
                        }).catch(error => {
                            console.log(error);
                        });
                },

                swipeLeft() {
                    const container = this.$refs.swiperContainer;

                    container.scrollLeft -= this.offset;
                },

                swipeRight() {
                    const container = this.$refs.swiperContainer;

                    container.scrollLeft += this.offset;
                },
            },
        });
    </script>
@endPushOnce
