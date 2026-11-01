@props(['title', 'description' => '', 'src', 'layout' => 'rail', 'count' => 12, 'columns' => 4, 'navigationLink' => ''])

<v-ethereal-products title="{{ $title }}" description="{{ $description }}" src="{{ $src }}" layout="{{ $layout }}" :count="{{ $count }}" :columns="{{ $columns }}" navigation-link="{{ $navigationLink }}">
    <section class="cfh-feed cfh-feed-loading" aria-busy="true"><h2>{{ $title }}</h2><div class="cfh-feed-skeleton"><span></span><span></span><span></span><span></span></div></section>
</v-ethereal-products>

@pushOnce('scripts')
<script type="text/x-template" id="v-ethereal-products-template">
    <section v-if="isLoading || products.length || layout === 'grid'" class="cfh-feed" :class="['cfh-feed-layout-' + layout, 'cfh-feed-columns-' + columns]" :aria-busy="isLoading">
        <div class="cfh-feed-heading">
            <h2>@{{ title }}</h2>
            <p v-if="description">@{{ description }}</p>
            <a v-if="navigationLink" :href="navigationLink" class="cfh-feed-view">{{ trans('shop::app.components.products.carousel.view-all') }} <span class="icon-arrow-right" aria-hidden="true"></span></a>
        </div>
        <div v-if="isLoading" class="cfh-feed-skeleton"><span v-for="index in Math.min(count, 4)" :key="index"></span></div>
        <div v-else-if="products.length" ref="rail" :class="layout === 'grid' ? 'cfh-feed-grid' : 'cfh-feed-rail'">
            <x-shop::products.card v-for="product in products.slice(0, count)" ::key="product.id" />
        </div>
        <p v-else class="cfh-feed-empty">{{ trans('classy-fashion::storefront.empty_collection') }}</p>
        <div v-if="layout === 'rail' && products.length > 2" class="cfh-feed-navigation">
            <button type="button" aria-label="{{ trans('shop::app.components.products.carousel.previous') }}" @click="move(-1)"><span class="icon-arrow-left" aria-hidden="true"></span></button>
            <button type="button" aria-label="{{ trans('shop::app.components.products.carousel.next') }}" @click="move(1)"><span class="icon-arrow-right" aria-hidden="true"></span></button>
        </div>
    </section>
</script>
<script type="module">
    app.component('v-ethereal-products', {
        template: '#v-ethereal-products-template',
        props: ['title', 'description', 'src', 'layout', 'count', 'columns', 'navigationLink'],
        data() {
            return { isLoading: true, products: [] };
        },
        mounted() {
            this.$axios.get(this.src).then(response => {
                this.products = response.data.data;
            }).catch(() => {
                this.products = [];
            }).finally(() => {
                this.isLoading = false;
            });
        },
        methods: {
            move(direction) {
                const rail = this.$refs.rail;
                const card = rail?.firstElementChild;
                if (card) {
                    const gap = parseFloat(getComputedStyle(rail).gap) || 0;
                    rail.scrollBy({ left: direction * (card.getBoundingClientRect().width + gap), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
                }
            },
        },
    });
</script>
@endPushOnce
