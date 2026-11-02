@php
    use ClassyFashion\Support\Storefront;

    $channel = core()->getCurrentChannel();
    $hero = Storefront::image('hero_image');
    $collections = array_values(array_filter(Storefront::collections(), fn ($collection) => $collection['src'] !== ''));
    $editorialCollections = array_values(array_filter(Storefront::editorialCollections(), fn ($collection) => $collection['src'] !== ''));
@endphp

@push('meta')
    <meta name="title" content="{{ $channel->home_seo['meta_title'] ?? config('app.name') }}">
    <meta name="description" content="{{ $channel->home_seo['meta_description'] ?? '' }}">
    <meta name="keywords" content="{{ $channel->home_seo['meta_keywords'] ?? '' }}">
@endpush

@push('scripts')
    @if (! empty($categories))
        <script>localStorage.setItem('categories', JSON.stringify(@json($categories)));</script>
    @endif
@endpush

<x-shop::layouts>
    <x-slot:title>{{ $channel->home_seo['meta_title'] ?? config('app.name') }}</x-slot>

    <section class="cfh-campaign @if (! $hero) cfh-campaign-without-photo @endif" aria-labelledby="campaign-title" @if ($hero) style="background-image: url('{{ $hero }}')" @endif>
        @if ($hero)
        <img
            class="cfh-campaign-image"
            src="{{ $hero }}"
            sizes="100vw"
            width="1672"
            height="940"
            alt="{{ Storefront::get('hero_alt') }}"
            fetchpriority="high"
            loading="eager"
        >
        @endif
        <div class="cfh-campaign-copy">
            <h1 id="campaign-title">{{ Storefront::get('hero_title') }}</h1>
            <p>{{ Storefront::get('hero_text') }}</p>
            <a class="cfh-button" href="{{ Storefront::get('hero_link') }}">{{ Storefront::get('hero_button') }}</a>
        </div>
    </section>

    <section class="cfh-promotion">
        <div class="cfh-container cfh-promotion-inner">
            <div class="cfh-promotion-copy">
                <h2>{{ trans('classy-fashion::storefront.promotion_title') }}</h2>
                <p>{{ trans('classy-fashion::storefront.promotion_text') }}</p>
                <a class="cfh-light-button" href="{{ Storefront::get('hero_link') }}">{{ Storefront::get('hero_button') }}</a>
            </div>
            <div class="cfh-promotion-facts">
                @foreach (['UGX' => 'prices', 'Size' => 'sizes', 'Order' => 'orders', 'Help' => 'help'] as $label => $service)
                    <div><span>{{ trans('classy-fashion::storefront.facts.'.$service) }}</span><p>{{ trans('classy-fashion::storefront.services.'.$service) }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="cfh-container cfh-category-tiles" aria-labelledby="category-title">
        <div class="cfh-centered-heading"><h2 id="category-title">{{ trans('classy-fashion::storefront.categories') }}</h2><p>{{ Storefront::get('collection_text') }}</p></div>
        <div class="cfh-category-rail">
            @foreach (Storefront::collections() as $collection)
                <a href="{{ $collection['link'] }}">@if ($collection['src'])<img src="{{ $collection['src'] }}" width="200" height="200" loading="lazy" alt="{{ $collection['alt'] }}">@endif<span>{{ $collection['title'] }}</span></a>
            @endforeach
        </div>
    </section>

    <div class="cfh-container">
        <x-shop::products.ethereal-feed :title="trans('classy-fashion::storefront.new_arrivals')" :description="Storefront::get('collection_text')" :src="route('shop.api.products.index', ['sort' => 'created_at-desc', 'limit' => 24])" layout="grid" :count="12" :columns="3" />
    </div>

    @if ($collections)
    <section class="cfh-editorial-splits">
        @foreach (array_slice($collections, 0, 2) as $collection)
            <div class="cfh-editorial-split @if ($loop->even) cfh-editorial-reverse @endif">
                <img src="{{ $collection['src'] }}" alt="{{ $collection['alt'] }}" width="983" height="593" loading="lazy">
                <div class="cfh-editorial-copy"><p>{{ trans('classy-fashion::storefront.local') }}</p><h2>{{ $collection['title'] }}</h2><a class="cfh-button" href="{{ $collection['link'] }}">{{ trans('classy-fashion::storefront.discover') }}</a></div>
            </div>
        @endforeach
    </section>

    <section class="cfh-container cfh-editorial-collections">
        <div class="cfh-centered-heading"><h2>{{ Storefront::get('collection_title') }}</h2><p>{{ Storefront::get('collection_text') }}</p></div>
        <div class="cfh-editorial-grid">
            @foreach ($editorialCollections as $collection)
                <a href="{{ $collection['link'] }}"><div class="cfh-editorial-square"><img src="{{ $collection['src'] }}" alt="{{ $collection['alt'] }}" width="432" height="432" loading="lazy"></div><h3>{{ $collection['title'] }}</h3><p>{{ trans('classy-fashion::storefront.discover') }}</p></a>
            @endforeach
        </div>
    </section>

    <section class="cfh-container cfh-lookbook-pair">
        @foreach (array_slice($collections, 0, 2) as $collection)
            <a href="{{ $collection['link'] }}"><img src="{{ $collection['src'] }}" width="672" height="748" alt="{{ $collection['alt'] }}" loading="lazy"><div><h2>{{ $collection['title'] }}</h2><span>{{ trans('classy-fashion::storefront.discover') }}</span></div></a>
        @endforeach
    </section>
    @endif

    @if (Storefront::image('everyday_image'))
    <section class="cfh-featured-split">
        <img src="{{ Storefront::image('everyday_image') }}" width="983" height="1020" alt="{{ Storefront::get('everyday_alt') }}" loading="lazy">
        <div><x-shop::products.ethereal-feed :title="trans('classy-fashion::storefront.featured_styles')" :src="route('shop.api.products.index', ['sort' => 'created_at-desc', 'limit' => 12])" :navigation-link="route('shop.search.index')" layout="rail" /></div>
    </section>
    @endif

    <div class="cfh-offset-rail"><x-shop::products.ethereal-feed :title="trans('classy-fashion::storefront.everyday_collection')" :description="Storefront::get('collection_text')" :src="route('shop.api.products.index', ['sort' => 'name-asc', 'limit' => 12])" :navigation-link="route('shop.search.index')" layout="rail" /></div>

    @if ($hero)
        <section class="cfh-story-banner" style="background-image: url('{{ $hero }}')"><div><p>{{ trans('classy-fashion::storefront.local') }}</p><h2>{{ Storefront::get('hero_title') }}</h2><a class="cfh-button" href="{{ Storefront::get('hero_link') }}">{{ Storefront::get('hero_button') }}</a></div></section>
    @endif

    <section class="cfh-container cfh-shop-strip"><h2>{{ trans('classy-fashion::storefront.shop_by_style') }}</h2><div>@foreach (['dresses', 'shirts', 'caps', 'bags', 'shoes'] as $style)<a href="{{ route('shop.search.index', ['query' => $style]) }}">{{ trans('classy-fashion::storefront.editorial.'.$style) }}</a>@endforeach</div></section>

    <div class="cfh-container"><x-shop::products.ethereal-feed :title="trans('classy-fashion::storefront.explore_more')" :description="Storefront::get('collection_text')" :src="route('shop.api.products.index', ['sort' => 'price-asc', 'limit' => 12])" layout="grid" :count="12" :columns="4" /></div>

    @if ($collections)
        <section class="cfh-container cfh-shop-journal"><div class="cfh-centered-heading"><h2>{{ trans('classy-fashion::storefront.from_the_shop') }}</h2><p>{{ Storefront::get('collection_text') }}</p></div><div class="cfh-journal-masonry">@foreach ([0, 1, 2, 0, 2, 1, 0] as $photo)@php($collection = $collections[$photo % count($collections)])<a href="{{ $collection['link'] }}"><img src="{{ $collection['src'] }}" width="316" height="{{ $loop->odd ? 560 : 316 }}" alt="{{ $collection['alt'] }}" loading="lazy"></a>@endforeach</div></section>
    @endif

    @if ($preview ?? false)
        @include('shop::home.preview-bridge')
    @endif
</x-shop::layouts>
