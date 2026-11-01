<section class="container cfh-product-recommendations" aria-labelledby="product-recommendations-title">
    <h2 id="product-recommendations-title">{{ trans('shop::app.products.view.related-product-title') }}</h2>
    <div class="cfh-product-recommendation-rail">
        @foreach ($picks as $pick)
            @php ($image = product_image()->getProductBaseImage($pick))
            <article class="cfh-product-recommendation">
                <a href="{{ route('shop.product_or_category.index', $pick->url_key) }}">
                    <img src="{{ $image['large_image_url'] }}" alt="{{ $image['alt'] ?: $pick->name }}" width="432" height="529" loading="lazy" decoding="async">
                    <h3>{{ $pick->name }}</h3>
                </a>
                <div class="cfh-product-recommendation-price">{!! $pick->getTypeInstance()->getPriceHtml() !!}</div>
            </article>
        @endforeach
    </div>
</section>
