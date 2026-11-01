<div class="cfh-product-detail-panels">
    {!! view_render_event('bagisto.shop.products.view.description.before', ['product' => $product]) !!}

    <details class="cfh-product-detail-panel">
        <summary>{{ trans('shop::app.products.view.description') }}</summary>
        <div class="cfh-product-detail-panel-content">{!! $product->description !!}</div>
    </details>

    {!! view_render_event('bagisto.shop.products.view.description.after', ['product' => $product]) !!}

    @if (count($attributeData))
        <details class="cfh-product-detail-panel">
            <summary>{{ trans('shop::app.products.view.additional-information') }}</summary>
            <dl class="cfh-product-detail-attributes">
                @foreach ($attributeData as $attribute)
                    <dt>{{ $attribute['label'] }}</dt>
                    <dd>
                        @if ($attribute['type'] === 'file')
                            <a href="{{ Storage::url($product[$attribute['code']]) }}" download="{{ $attribute['label'] }}">{{ $attribute['label'] }}</a>
                        @elseif ($attribute['type'] === 'image')
                            <a href="{{ Storage::url($product[$attribute['code']]) }}" download="{{ $attribute['label'] }}"><img src="{{ Storage::url($attribute['value']) }}" alt="{{ $attribute['label'] }}" width="40" height="40" loading="lazy"></a>
                        @else
                            {{ $attribute['value'] }}
                        @endif
                    </dd>
                @endforeach
            </dl>
        </details>
    @endif

    <details id="product-reviews-panel" class="cfh-product-detail-panel">
        <summary id="review-accordian-button">{{ trans('shop::app.products.view.review') }}</summary>
        <div class="cfh-product-detail-panel-content cfh-product-detail-reviews">@include('shop::products.view.reviews')</div>
    </details>
</div>
