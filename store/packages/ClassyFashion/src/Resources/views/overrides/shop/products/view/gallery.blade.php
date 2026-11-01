<v-product-gallery ref="gallery" :is-wishlist="isWishlist" @wishlist="addToWishlist">
    <x-shop::shimmer.products.gallery />
</v-product-gallery>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-product-gallery-template"
    >
        <div class="cfh-product-gallery">
            @include('shop::products.view.gallery.desktop')


            @include('shop::products.view.gallery.mobile')


            <x-shop::image-zoomer
                ::attachments="attachments"
                ::is-image-zooming="isImageZooming"
                ::initial-index="`media_${activeIndex}`"
            />
        </div>
    </script>

    <script type="module">
        app.component('v-product-gallery', {
            template: '#v-product-gallery-template',

            props: { isWishlist: Boolean },

            data() {
                return {
                    isImageZooming: false,

                    isMediaLoading: true,

                    placeholderUrl: @js(product_image()->getPlaceholderUrl('large')),

                    media: {
                        images: @json(product_image()->getGalleryImages($product)),

                        videos: @json(product_video()->getVideos($product)),
                    },

                    baseFile: {
                        type: '',

                        path: '',

                        alt: "{{ $product->name }}"
                    },

                    activeIndex: 0,

                    containerOffset: 110,
                };
            },

            watch: {
                'media.images': {
                    deep: true,

                    handler(newImages, oldImages) {
                        this.activeIndex = Math.min(this.activeIndex, Math.max((newImages?.length ?? 1) - 1, 0));
                        let selectedImage = newImages?.[this.activeIndex];

                        if (JSON.stringify(newImages) !== JSON.stringify(oldImages) && selectedImage?.large_image_url) {
                            this.baseFile.path = selectedImage.large_image_url;

                            this.baseFile.alt = selectedImage.alt;
                            this.$nextTick(() => this.selectMobileMedia(this.activeIndex));
                        }
                    },
                },
            },

            mounted() {
                if (this.media.images.length) {

                    this.baseFile.type = 'image';

                    this.baseFile.path = this.media.images[0].large_image_url;

                    this.baseFile.alt = this.media.images[0].alt;
                } else if (this.media.videos.length) {

                    this.baseFile.type = 'video';

                    this.baseFile.path = this.media.videos[0].video_url;
                }
            },

            computed: {
                galleryMedia() {
                    const items = [...this.media.images, ...this.media.videos];

                    return items.length ? items : [{ type: 'images', large_image_url: this.placeholderUrl, original_image_url: this.placeholderUrl, alt: @js($product->name) }];
                },

                lengthOfMedia() {
                    if (this.media.images.length) {
                        return [...this.media.images, ...this.media.videos].length > 5;
                    }
                },

                attachments() {
                    return this.galleryMedia.map(media => ({
                        url: media.type === 'videos' ? media.video_url : media.original_image_url,

                        type: media.type === 'videos' ? 'video' : 'image',

                        alt: media.alt ?? "{{ $product->name }}",
                    }));
                },
            },

            methods: {
                openMedia(index) {
                    this.activeIndex = index;
                    this.isImageZooming = true;
                },

                selectMobileMedia(index) {
                    const rail = this.$refs.mobileRail;

                    if (rail) {
                        const direction = document.dir === 'rtl' ? -1 : 1;
                        rail.scrollTo({ left: direction * index * rail.clientWidth, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                    }
                },

                syncMobileIndex() {
                    const rail = this.$refs.mobileRail;

                    if (rail?.clientWidth) {
                        this.activeIndex = Math.round(Math.abs(rail.scrollLeft) / rail.clientWidth);
                    }
                },

                isActiveMedia(index) {
                    return index === this.activeIndex;
                },

                onMediaLoad() {
                    this.isMediaLoading = false;
                },

                onMediaError(event) {
                    this.isMediaLoading = false;

                    if (event?.target && event.target.src !== this.placeholderUrl) {
                        event.target.src = this.placeholderUrl;
                    }
                },

                change(media, index) {
                    this.isMediaLoading = true;

                    if (media.type == 'videos') {
                        this.baseFile.type = 'video';

                        this.baseFile.path = media.video_url;

                        this.onMediaLoad();
                    } else {
                        this.baseFile.type = 'image';

                        this.baseFile.path = media.large_image_url;

                        this.baseFile.alt = media.alt;
                    }

                    this.activeIndex = index;
                    this.selectMobileMedia(index);
                },

                swipeTop() {
                    this.selectMobileMedia(Math.max(this.activeIndex - 1, 0));
                },

                swipeDown() {
                    this.selectMobileMedia(Math.min(this.activeIndex + 1, this.galleryMedia.length - 1));
                },
            },
        });
    </script>
@endpushOnce
