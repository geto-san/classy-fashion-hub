@php
    use ClassyFashion\Support\Storefront;

    $channel = core()->getCurrentChannel();
    $images = Storefront::homepageImages();
    $hero = $images['hero_image'];
    $closing = $images['closing_image'];
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

    <section class="cfh-campaign @if (! $hero) cfh-campaign-without-photo @endif" aria-labelledby="campaign-title">
        @if ($hero)
            <img class="cfh-campaign-image" src="{{ $hero }}" width="1672" height="940" alt="{{ Storefront::get('hero_alt') }}" fetchpriority="high" loading="eager">
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
                @foreach (['prices', 'sizes', 'orders', 'help'] as $service)
                    <div><span>{{ trans('classy-fashion::storefront.facts.'.$service) }}</span><p>{{ trans('classy-fashion::storefront.services.'.$service) }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="cfh-container cfh-category-tiles" aria-labelledby="category-title">
        <div class="cfh-centered-heading">
            <h2 id="category-title">{{ trans('classy-fashion::storefront.categories') }}</h2>
            <p>{{ Storefront::get('collection_text') }}</p>
        </div>
        <div class="cfh-category-rail">
            @foreach (Storefront::collections() as $collection)
                <a href="{{ $collection['link'] }}">
                    @if ($collection['src'])
                        <img src="{{ $collection['src'] }}" width="200" height="200" loading="lazy" alt="{{ $collection['alt'] }}">
                    @endif
                    <span>{{ $collection['title'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <div class="cfh-container">
        <x-shop::products.ethereal-feed :title="trans('classy-fashion::storefront.new_arrivals')" :description="Storefront::get('collection_text')" :src="route('shop.api.products.index', ['sort' => 'created_at-desc', 'limit' => 24])" layout="grid" :count="12" :columns="3" />
    </div>

    <section class="cfh-container cfh-shop-strip">
        <h2>{{ trans('classy-fashion::storefront.shop_by_style') }}</h2>
        <div>
            @foreach (['dresses', 'shirts', 'caps', 'bags', 'shoes'] as $style)
                <a href="{{ route('shop.search.index', ['query' => $style]) }}">{{ trans('classy-fashion::storefront.editorial.'.$style) }}</a>
            @endforeach
        </div>
    </section>

    @if ($closing)
        <section class="cfh-closing-banner" aria-labelledby="closing-title">
            <img src="{{ $closing }}" width="1672" height="940" alt="{{ Storefront::get('closing_alt') }}" loading="lazy" decoding="async">
            <div class="cfh-closing-copy">
                <h2 id="closing-title">{{ Storefront::get('collection_title') }}</h2>
                <a class="cfh-button" href="{{ Storefront::get('hero_link') }}">{{ Storefront::get('hero_button') }}</a>
            </div>
        </section>
    @endif

    @if ($preview ?? false)
        @include('shop::home.preview-bridge')
    @endif
</x-shop::layouts>
