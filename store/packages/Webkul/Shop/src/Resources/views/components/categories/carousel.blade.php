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
        <section
            class="cfh-section"
            v-if="! isLoading && categories?.length"
        >
            <header class="cfh-section__head">
                <div>
                    <p class="cfh-eyebrow">Item category</p>

                    <h2 class="cfh-title" v-text="title || 'Shop by category'"></h2>
                </div>
            </header>

            <div class="cfh-tiles">
                <a
                    class="cfh-tile"
                    v-for="category in categories"
                    :href="'/' + category.slug"
                    :aria-label="category.name"
                >
                    <span class="cfh-tile__img">
                        <x-shop::media.images.lazy
                            ::src="category.logo?.large_image_url || fallback"
                            ::srcset="`
                                ${(category.logo?.medium_image_url || fallback)} 300w,
                                ${(category.logo?.large_image_url || fallback)} 600w
                            `"
                            sizes="(max-width: 768px) 45vw, 20vw"
                            width="300"
                            height="400"
                            ::alt="category.name"
                        />
                    </span>

                    <span class="cfh-tile__label">
                        <span v-text="category.name"></span>
                        <span aria-hidden="true">+</span>
                    </span>
                </a>
            </div>
        </section>

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
