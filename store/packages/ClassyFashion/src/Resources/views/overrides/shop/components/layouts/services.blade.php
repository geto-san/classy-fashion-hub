{!! view_render_event('bagisto.shop.layout.features.before') !!}

<div class="container cfh-services">
    @foreach (['prices' => 'icon-dollar-sign', 'sizes' => 'icon-product', 'orders' => 'icon-orders', 'help' => 'icon-support'] as $service => $icon)
        <div class="cfh-service">
            <span class="{{ $icon }}" aria-hidden="true"></span>
            <p>{{ trans('classy-fashion::storefront.services.'.$service) }}</p>
        </div>
    @endforeach
</div>

{!! view_render_event('bagisto.shop.layout.features.after') !!}
