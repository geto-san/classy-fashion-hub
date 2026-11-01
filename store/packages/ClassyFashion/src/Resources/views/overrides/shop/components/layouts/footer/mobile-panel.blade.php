@inject('sectionRepository', 'Webkul\Theme\Repositories\SectionRepository')

@php
    $channel = core()->getCurrentChannel();
    $section = $sectionRepository->findOneOfType(
        \Webkul\Theme\Enums\SectionTypeEnum::FOOTER_LINKS->value,
        $channel->id,
        $channel->theme,
        app()->getLocale()
    );
    $links = collect($section?->getTypeInstance()?->sanitize((array) $section->options) ?? [])
        ->flatten(1)
        ->filter(fn ($link) => filled($link['title'] ?? null));
@endphp

<div class="cfh-footer-panel">
    <img src="{{ asset('images/brand-logo.png') }}" width="164" height="55" alt="{{ config('app.name') }}" loading="lazy">
    <p>{{ trans('classy-fashion::storefront.footer_description') }}</p>
    <ul>
        @foreach ($links as $link)
            <li><a href="{{ $link['url'] }}">{{ $link['title'] }}</a></li>
        @endforeach
    </ul>
    {!! view_render_event('bagisto.shop.layout.footer.newsletter_subscription.before') !!}
    @if (core()->getConfigData('customer.settings.newsletter.subscription'))
        <div class="cfh-footer-subscription">
            <h3>{{ trans('classy-fashion::storefront.newsletter_title') }}</h3>
            <p>{{ trans('classy-fashion::storefront.newsletter_text') }}</p>
            <x-shop::form :action="route('shop.subscription.store')">
                <x-shop::form.control-group.control
                    type="email"
                    name="email"
                    rules="required|email"
                    :label="trans('shop::app.components.layouts.footer.email')"
                    :aria-label="trans('shop::app.components.layouts.footer.email')"
                    placeholder="email@example.com"
                />
                <x-shop::form.control-group.error control-name="email" />
                <button type="submit" class="primary-button">@lang('shop::app.components.layouts.footer.subscribe')</button>
            </x-shop::form>
        </div>
    @endif
    {!! view_render_event('bagisto.shop.layout.footer.newsletter_subscription.after') !!}
</div>
