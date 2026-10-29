@php
    $channel = core()->getCurrentChannel();

    $sections = collect($sections ?? []);

    $heroSlides = $sections->filter(fn ($section) => $section->type === \Webkul\Theme\Enums\SectionTypeEnum::IMAGE_CAROUSEL->value);

    $productRails = $sections->filter(fn ($section) => $section->type === \Webkul\Theme\Enums\SectionTypeEnum::PRODUCT_CAROUSEL->value);
@endphp

<!-- SEO Meta Content -->
@push ('meta')
    <meta
        name="title"
        content="{{ $channel->home_seo['meta_title'] ?? '' }}"
    />

    <meta
        name="description"
        content="{{ $channel->home_seo['meta_description'] ?? '' }}"
    />

    <meta
        name="keywords"
        content="{{ $channel->home_seo['meta_keywords'] ?? '' }}"
    />
@endPush

@push('scripts')
    @if(! empty($categories))
        <script>
            localStorage.setItem('categories', JSON.stringify(@json($categories)));
        </script>
    @endif
@endpush

<x-shop::layouts>
    <!-- Page Title -->
    <x-slot:title>
        {{  $channel->home_seo['meta_title'] ?? '' }}
    </x-slot>

    {{-- 1. Hero: admin-managed slides when present, otherwise the Classy editorial hero --}}
    @forelse ($heroSlides as $section)
        @php ($data = $section->options)

        @if ($preview ?? false)
            <div data-section-id="{{ $section->id }}" data-section-name="{{ $section->name }}">
        @endif

        <x-shop::carousel
            :options="$section->getTypeInstance()?->sanitize((array) $data) ?? $data"
            aria-label="{{ trans('shop::app.home.index.image-carousel') }}"
        />

        @if ($preview ?? false)
            </div>
        @endif
    @empty
        <section class="cfh-hero" aria-label="Classy Fashion Hub">
            <div class="cfh-hero__copy">
                <p class="cfh-eyebrow">Autumn / Winter {{ date('Y') }}</p>

                <h1 class="cfh-hero__title">
                    Quiet style,<br>
                    made for Uganda.
                </h1>

                <p class="cfh-hero__text">
                    Shirts, jackets, shoes and dresses for campus, work and the weekend.
                    Clear prices in UGX, delivered across the country.
                </p>

                <div class="cfh-hero__cta">
                    <a class="cfh-btn" href="{{ route('shop.search.index', ['new' => 1]) }}">
                        Shop new arrivals
                    </a>

                    <a class="cfh-btn cfh-btn--ghost" href="{{ route('shop.search.index', ['featured' => 1]) }}">
                        Staff picks
                    </a>
                </div>
            </div>

            <div class="cfh-hero__panel" aria-hidden="true">
                <span class="cfh-hero__mark">C</span>
                <span class="cfh-hero__caption">Classy Fashion Hub, Mbarara</span>
            </div>
        </section>
    @endforelse

    {{-- 2. Category tiles --}}
    <x-shop::categories.carousel
        title="Shop by category"
        :src="route('shop.api.categories.index', ['parent_id' => $channel->root_category_id])"
        :navigation-link="route('shop.home.index')"
        aria-label="{{ trans('shop::app.home.index.categories-carousel') }}"
    />

    {{-- 3. Product rails: admin-managed when present, otherwise sensible defaults --}}
    @forelse ($productRails as $section)
        @php ($data = $section->options)

        @if ($preview ?? false)
            <div data-section-id="{{ $section->id }}" data-section-name="{{ $section->name }}">
        @endif

        <x-shop::products.carousel
            :title="$data['title'] ?? ''"
            :src="route('shop.api.products.index', $data['filters'] ?? [])"
            :navigation-link="route('shop.search.index', $data['filters'] ?? [])"
            aria-label="{{ trans('shop::app.home.index.product-carousel') }}"
        />

        @if ($preview ?? false)
            </div>
        @endif
    @empty
        <x-shop::products.carousel
            title="New arrivals"
            eyebrow="New"
            :src="route('shop.api.products.index', ['new' => 1, 'sort' => 'created_at-desc', 'limit' => 12])"
            :navigation-link="route('shop.search.index', ['new' => 1])"
            aria-label="{{ trans('shop::app.home.index.product-carousel') }}"
        />

        <x-shop::products.carousel
            title="Staff picks"
            eyebrow="Featured"
            :src="route('shop.api.products.index', ['featured' => 1, 'limit' => 12])"
            :navigation-link="route('shop.search.index', ['featured' => 1])"
            aria-label="{{ trans('shop::app.home.index.product-carousel') }}"
        />
    @endforelse

    {{-- 4. Editorial pair --}}
    <section class="cfh-section cfh-editorial" aria-label="Featured collections">
        <a class="cfh-editorial__item cfh-editorial__item--dark" href="{{ route('shop.search.index', ['query' => 'shirt']) }}">
            <span class="cfh-eyebrow">Campus edit</span>
            <span class="cfh-editorial__title">Student essentials</span>
            <span class="cfh-editorial__text">Easy shirts, jackets and shoes that work from lecture hall to weekend.</span>
            <span class="cfh-more">Shop the edit +</span>
        </a>

        <a class="cfh-editorial__item" href="{{ route('shop.search.index', ['query' => 'dress']) }}">
            <span class="cfh-eyebrow">Occasion edit</span>
            <span class="cfh-editorial__title">Dressed for the occasion</span>
            <span class="cfh-editorial__text">Dresses and smart pieces for graduations, introductions and church.</span>
            <span class="cfh-more">Shop the edit +</span>
        </a>
    </section>

    {{-- 5. Store story --}}
    <section class="cfh-section cfh-story">
        <p class="cfh-eyebrow">About us</p>

        <h2 class="cfh-title">A Mbarara shop, now delivering countrywide</h2>

        <p>
            Classy Fashion Hub serves university students, school leavers and professionals.
            Every item has one fixed price in Uganda Shillings, so you always know what you pay.
            Order online, pay with mobile money, and we deliver to Kampala, Mbarara, Masaka, Entebbe and beyond.
        </p>

        <a class="cfh-more" href="{{ url('page/about-us') }}">Our story +</a>
    </section>

    @if ($preview ?? false)
        @include('shop::home.preview-bridge')
    @endif
</x-shop::layouts>
