<nav class="cfh-bottom-nav" aria-label="{{ trans('classy-fashion::storefront.menu') }}">
    <a class="cfh-bottom-item" href="{{ route('shop.home.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m3 10 9-7 9 7v11h-7v-7h-4v7H3Z"/></svg><span>{{ trans('classy-fashion::storefront.home') }}</span></a>
    <x-shop::drawer position="bottom" width="100%">
        <x-slot:toggle><button type="button" class="cfh-bottom-item"><span class="icon-hamburger" aria-hidden="true"></span><span>{{ trans('classy-fashion::storefront.menu') }}</span></button></x-slot:toggle>
        <x-slot:header><h2>{{ trans('classy-fashion::storefront.categories') }}</h2></x-slot:header>
        <x-slot:content><v-mobile-category></v-mobile-category></x-slot:content>
    </x-shop::drawer>
    @if (core()->getConfigData('catalog.products.settings.compare_option'))<a class="cfh-bottom-item" href="{{ route('shop.compare.index') }}"><span class="icon-compare" aria-hidden="true"></span><span>{{ trans('classy-fashion::storefront.header_compare') }}</span></a>@endif
    <x-shop::drawer position="bottom" width="100%">
        <x-slot:toggle><button type="button" class="cfh-bottom-item"><span class="icon-dollar-sign" aria-hidden="true"></span><span>{{ trans('classy-fashion::storefront.currencies') }}</span></button></x-slot:toggle>
        <x-slot:header><h2>{{ trans('classy-fashion::storefront.currencies') }}</h2></x-slot:header>
        <x-slot:content><div class="cfh-mobile-options">@foreach (core()->getCurrentChannel()->currencies as $currency)<a href="{{ request()->fullUrlWithQuery(['currency' => $currency->code]) }}">{{ $currency->symbol }} {{ $currency->code }}</a>@endforeach</div></x-slot:content>
    </x-shop::drawer>
    <x-shop::drawer position="bottom" width="100%">
        <x-slot:toggle><button type="button" class="cfh-bottom-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/></svg><span>{{ trans('classy-fashion::storefront.locales') }}</span></button></x-slot:toggle>
        <x-slot:header><h2>{{ trans('classy-fashion::storefront.locales') }}</h2></x-slot:header>
        <x-slot:content><div class="cfh-mobile-options">@foreach (core()->getCurrentChannel()->locales as $locale)<a href="{{ request()->fullUrlWithQuery(['locale' => $locale->code]) }}">{{ $locale->name }}</a>@endforeach</div></x-slot:content>
    </x-shop::drawer>
    <x-shop::drawer position="bottom" width="100%">
        <x-slot:toggle><button type="button" class="cfh-bottom-item"><span class="icon-more" aria-hidden="true"></span><span>{{ trans('classy-fashion::storefront.footer') }}</span></button></x-slot:toggle>
        <x-slot:header><h2>{{ config('app.name') }}</h2></x-slot:header>
        <x-slot:content>@include('shop::components.layouts.footer.mobile-panel')</x-slot:content>
    </x-shop::drawer>
</nav>
