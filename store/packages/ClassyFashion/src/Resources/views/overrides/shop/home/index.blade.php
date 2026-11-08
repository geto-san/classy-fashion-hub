@php
    use ClassyFashion\Support\Storefront;

    $channel = core()->getCurrentChannel();
    $images = Storefront::homepageImages();
    $hero = $images['hero_image'];
    $closing = $images['closing_image'];
    $slides = Storefront::campaignSlides();
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

    @if ($slides)
        @include('classy-fashion::campaign')
    @else
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
    @endif

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
        <x-shop::products.ethereal-feed :title="trans('classy-fashion::storefront.new_arrivals')" :description="Storefront::get('collection_text')" :src="route('classy.collections.index', ['collection' => 'arrivals'])" layout="grid" :count="12" :columns="3" />
    </div>

    <section class="cfh-editorial-splits">
        @foreach (['women' => 'women_editorial', 'men' => 'men_editorial'] as $department => $slot)
            @if ($images[$slot.'_image'])
                <div class="cfh-editorial-split @if ($loop->even) cfh-editorial-reverse @endif">
                    <img src="{{ $images[$slot.'_image'] }}" alt="{{ Storefront::get($slot.'_alt') }}" width="983" height="593" loading="lazy">
                    <div class="cfh-editorial-copy">
                        <h2>{{ trans('classy-fashion::coursework.editorial.'.$department) }}</h2>
                        <a class="cfh-button" href="/{{ $department }}">{{ trans('classy-fashion::storefront.discover') }}</a>
                    </div>
                </div>
            @endif
        @endforeach
    </section>

    <section class="cfh-container cfh-editorial-collections">
        <div class="cfh-centered-heading"><h2>{{ Storefront::get('collection_title') }}</h2><p>{{ Storefront::get('collection_text') }}</p></div>
        <div class="cfh-editorial-grid">
            @foreach (Storefront::editorialCollections() as $collection)
                @if ($collection['src'])
                    <a href="{{ $collection['link'] }}"><div class="cfh-editorial-square"><img src="{{ $collection['src'] }}" alt="{{ $collection['alt'] }}" width="432" height="432" loading="lazy"></div><h3>{{ $collection['title'] }}</h3><p>{{ trans('classy-fashion::storefront.discover') }}</p></a>
                @endif
            @endforeach
        </div>
    </section>

    <section class="cfh-container cfh-lookbook-pair">
        @foreach (['women' => 'women_lookbook', 'men' => 'men_lookbook'] as $department => $slot)
            @if ($images[$slot.'_image'])
                <a href="/{{ $department }}"><img src="{{ $images[$slot.'_image'] }}" width="672" height="748" alt="{{ Storefront::get($slot.'_alt') }}" loading="lazy"><div><h2>{{ trans('classy-fashion::coursework.editorial.'.$department) }}</h2><span>{{ trans('classy-fashion::storefront.discover') }}</span></div></a>
            @endif
        @endforeach
    </section>

    @if ($images['featured_story_image'])
        <section class="cfh-featured-split">
            <img src="{{ $images['featured_story_image'] }}" width="983" height="1020" alt="{{ Storefront::get('featured_story_alt') }}" loading="lazy">
            <div><x-shop::products.ethereal-feed :title="Storefront::get('outerwear_title')" :src="route('classy.collections.index', ['collection' => 'layers'])" :navigation-link="Storefront::get('outerwear_link')" layout="rail" :count="8" /></div>
        </section>
    @endif

    <div class="cfh-offset-rail"><x-shop::products.ethereal-feed :title="trans('classy-fashion::storefront.everyday_collection')" :description="Storefront::get('collection_text')" :src="route('classy.collections.index', ['collection' => 'everyday'])" :navigation-link="route('shop.search.index')" layout="rail" :count="12" /></div>

    <section class="cfh-container cfh-shop-strip">
        <h2>{{ trans('classy-fashion::storefront.shop_by_style') }}</h2>
        <div>
            @foreach (['dresses', 'shirts', 'caps', 'bags', 'shoes'] as $style)
                <a href="{{ route('shop.search.index', ['query' => $style]) }}">{{ trans('classy-fashion::storefront.editorial.'.$style) }}</a>
            @endforeach
        </div>
    </section>

    <div class="cfh-container"><x-shop::products.ethereal-feed :title="trans('classy-fashion::coursework.finishing')" :description="Storefront::get('collection_text')" :src="route('classy.collections.index', ['collection' => 'finishing'])" layout="grid" :count="8" :columns="4" /></div>

    <section class="cfh-container cfh-shop-journal">
        <div class="cfh-centered-heading"><h2>{{ trans('classy-fashion::coursework.journal') }}</h2><p>{{ trans('classy-fashion::coursework.journal_text') }}</p></div>
        <div class="cfh-journal-masonry">
            @foreach (['one', 'two', 'three', 'four', 'five', 'six'] as $number)
                @php($slot = 'journal_'.$number)
                @if ($images[$slot.'_image'])
                    <a href="{{ route('shop.search.index') }}"><img src="{{ $images[$slot.'_image'] }}" width="316" height="{{ $loop->odd ? 560 : 316 }}" alt="{{ Storefront::get($slot.'_alt') }}" loading="lazy"></a>
                @endif
            @endforeach
        </div>
    </section>

    @if ($closing)
        <section class="cfh-closing-banner" aria-labelledby="closing-title">
            <img src="{{ $closing }}" width="1672" height="940" alt="{{ Storefront::get('closing_alt') }}" loading="lazy" decoding="async">
            <div class="cfh-closing-copy">
                <h2 id="closing-title">{{ Storefront::get('closing_title') }}</h2>
                <a class="cfh-button" href="{{ Storefront::get('hero_link') }}">{{ Storefront::get('hero_button') }}</a>
            </div>
        </section>
    @endif

    @if ($preview ?? false)
        @include('shop::home.preview-bridge')
    @endif
</x-shop::layouts>
