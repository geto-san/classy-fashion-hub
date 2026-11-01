@props(['options'])

@php
    $images = array_values(array_filter($options['images'] ?? [], fn ($image) => filled($image['src'] ?? null)));
    $firstImage = $images[0] ?? null;
@endphp

@if ($firstImage)
    @push('meta')
        <link rel="preload" as="image" href="{{ $firstImage['src'] }}" fetchpriority="high">
    @endpush

    <v-carousel :images="{{ Illuminate\Support\Js::from($images) }}" {{ $attributes }}>
        <div class="overflow-hidden">
            <img
                src="{{ $firstImage['src'] }}"
                alt="{{ $firstImage['title'] }}"
                class="aspect-[2.743/1] max-h-screen w-screen select-none object-cover"
                style="width:100%;aspect-ratio:2.743/1;object-fit:cover;display:block"
                fetchpriority="high"
                width="1920"
                height="700"
            >
        </div>
    </v-carousel>
@endif

@pushOnce('scripts')
    <script type="text/x-template" id="v-carousel-template">
        <div
            class="relative m-auto flex w-full overflow-hidden"
            role="region"
            @mouseenter="hovered = true; stop()"
            @mouseleave="hovered = false; play()"
            @focusin="focused = true; stop()"
            @focusout="onFocusOut"
            @keydown.left.prevent="navigate(direction === 'rtl' ? 1 : -1)"
            @keydown.right.prevent="navigate(direction === 'rtl' ? -1 : 1)"
            @pointerdown="dragStart"
            @pointermove="dragMove"
            @pointerup="dragEnd"
            @pointercancel="dragCancel"
            style="touch-action:pan-y"
        >
            <div
                ref="track"
                class="inline-flex w-full transition-transform duration-700 ease-out will-change-transform"
                :style="{ transform: 'translateX(' + offset + 'px)', transitionDuration: dragging || reducedMotion ? '0ms' : '' }"
            >
                <a
                    v-for="(image, index) in images"
                    :key="image.src"
                    :href="image.link || null"
                    :tabindex="index === currentIndex ? 0 : -1"
                    :aria-hidden="index !== currentIndex"
                    class="max-h-screen w-full shrink-0 bg-cover bg-no-repeat"
                    @click.capture="onSlideClick"
                    @dragstart.prevent
                >
                    <img
                        :src="image.src"
                        :alt="image.title"
                        :loading="index === 0 ? 'eager' : 'lazy'"
                        :fetchpriority="index === 0 ? 'high' : 'low'"
                        class="aspect-[2.743/1] max-h-full w-full select-none object-cover"
                        width="1920"
                        height="700"
                        draggable="false"
                    >
                </a>
            </div>

            <button
                v-if="images.length > 1"
                type="button"
                class="icon-arrow-left absolute left-2.5 top-1/2 -mt-[22px] hidden w-auto rounded-full bg-black/80 p-3 text-2xl font-bold text-white opacity-30 transition-all hover:opacity-100 focus-visible:opacity-100 md:inline-block"
                aria-label="{{ trans('shop::app.components.carousel.previous') }}"
                @click="navigate(direction === 'rtl' ? 1 : -1)"
            ></button>

            <button
                v-if="images.length > 1"
                type="button"
                class="icon-arrow-right absolute right-2.5 top-1/2 -mt-[22px] hidden w-auto rounded-full bg-black/80 p-3 text-2xl font-bold text-white opacity-30 transition-all hover:opacity-100 focus-visible:opacity-100 md:inline-block"
                aria-label="{{ trans('shop::app.components.carousel.next') }}"
                @click="navigate(direction === 'rtl' ? -1 : 1)"
            ></button>

            <div v-if="images.length > 1" class="absolute bottom-5 left-0 flex w-full justify-center max-md:bottom-3.5 max-sm:bottom-2.5">
                <button
                    v-for="(image, index) in images"
                    :key="index"
                    type="button"
                    class="mx-1 h-3 w-3 cursor-pointer rounded-full p-2 sm:p-2.5 max-md:h-2 max-md:w-2 max-sm:h-1.5 max-sm:w-1.5"
                    :class="index === currentIndex ? 'bg-navyBlue' : 'bg-gray-500 opacity-30'"
                    :aria-label="'{{ trans('shop::app.components.carousel.image-slide') }} ' + (index + 1)"
                    :aria-current="index === currentIndex ? 'true' : null"
                    @click="goTo(index)"
                ></button>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-carousel', {
            template: '#v-carousel-template',

            props: {
                images: { type: Array, default: () => [] },
            },

            data() {
                return {
                    currentIndex: 0,
                    width: 0,
                    direction: document.documentElement.dir || 'ltr',
                    dragging: false,
                    pointerId: null,
                    startX: 0,
                    distance: 0,
                    suppressClick: false,
                    hovered: false,
                    focused: false,
                    reducedMotion: false,
                    timer: null,
                    resizeObserver: null,
                    motionQuery: null,
                };
            },

            computed: {
                offset() {
                    return this.currentIndex * this.width * (this.direction === 'rtl' ? 1 : -1) + this.distance;
                },
            },

            mounted() {
                this.width = this.$el.clientWidth;
                this.resizeObserver = new ResizeObserver(() => {
                    this.width = this.$el.clientWidth;
                });
                this.resizeObserver.observe(this.$el);
                this.motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
                this.motionQuery.addEventListener('change', this.onMotionChange);
                this.onMotionChange();
                document.addEventListener('visibilitychange', this.play);
            },

            beforeUnmount() {
                this.stop();
                this.resizeObserver?.disconnect();
                this.motionQuery?.removeEventListener('change', this.onMotionChange);
                document.removeEventListener('visibilitychange', this.play);
            },

            methods: {
                stop() {
                    clearInterval(this.timer);
                    this.timer = null;
                },

                play() {
                    this.stop();

                    if (this.images.length < 2 || this.hovered || this.focused || this.dragging || this.reducedMotion || document.hidden) {
                        return;
                    }

                    this.timer = setInterval(() => this.goTo(this.currentIndex + 1), 5000);
                },

                onMotionChange() {
                    this.reducedMotion = this.motionQuery.matches;
                    this.play();
                },

                onFocusOut(event) {
                    if (! this.$el.contains(event.relatedTarget)) {
                        this.focused = false;
                        this.play();
                    }
                },

                goTo(index) {
                    if (! this.images.length) {
                        return;
                    }

                    this.currentIndex = (index + this.images.length) % this.images.length;
                    this.distance = 0;
                    this.play();
                },

                navigate(step) {
                    this.goTo(this.currentIndex + step);
                },

                dragStart(event) {
                    if (! event.isPrimary || event.button !== 0 || event.target.closest('button') || this.images.length < 2) {
                        return;
                    }

                    this.suppressClick = false;
                    this.pointerId = event.pointerId;
                    this.startX = event.clientX;
                    this.distance = 0;
                    this.dragging = true;
                    this.stop();
                },

                dragMove(event) {
                    if (! this.dragging || this.pointerId !== event.pointerId) {
                        return;
                    }

                    this.distance = event.clientX - this.startX;

                    if (Math.abs(this.distance) > 8) {
                        this.suppressClick = true;
                        this.$el.setPointerCapture(event.pointerId);
                    }
                },

                dragEnd(event) {
                    if (! this.dragging || this.pointerId !== event.pointerId) {
                        return;
                    }

                    const step = Math.abs(this.distance) >= Math.min(100, this.width * 0.15)
                        ? (this.distance < 0 ? 1 : -1) * (this.direction === 'rtl' ? -1 : 1)
                        : 0;

                    this.dragging = false;
                    this.pointerId = null;
                    this.navigate(step);
                },

                dragCancel() {
                    this.dragging = false;
                    this.pointerId = null;
                    this.distance = 0;
                    this.play();
                },

                onSlideClick(event) {
                    if (this.suppressClick) {
                        event.preventDefault();
                        this.suppressClick = false;
                    }
                },
            },
        });
    </script>
@endpushOnce
