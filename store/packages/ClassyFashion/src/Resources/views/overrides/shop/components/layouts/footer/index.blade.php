{!! view_render_event('bagisto.shop.layout.footer.before') !!}

@inject('sectionRepository', 'Webkul\Theme\Repositories\SectionRepository')

@php
    $channel = core()->getCurrentChannel();
    $section = $sectionRepository->findOneOfType(
        \Webkul\Theme\Enums\SectionTypeEnum::FOOTER_LINKS->value,
        $channel->id,
        $channel->theme,
        app()->getLocale()
    );
    $columns = collect($section?->getTypeInstance()?->sanitize((array) $section->options) ?? [])
        ->map(fn ($links) => array_values(array_filter((array) $links, fn ($link) => filled($link['title'] ?? null))))
        ->filter()
        ->values();
    $accountLinks = [
        ['url' => route('shop.search.index'), 'title' => trans('classy-fashion::storefront.view_all')],
        ['url' => route('shop.customer.session.create'), 'title' => trans('shop::app.components.layouts.header.desktop.bottom.sign-in')],
        ['url' => route('shop.customers.register.index'), 'title' => trans('shop::app.components.layouts.header.desktop.bottom.sign-up')],
        ['url' => route('shop.customers.account.profile.index'), 'title' => trans('shop::app.components.layouts.header.desktop.bottom.profile')],
        ['url' => route('shop.customers.account.orders.index'), 'title' => trans('shop::app.components.layouts.header.desktop.bottom.orders')],
        ['url' => route('shop.home.contact_us'), 'title' => trans('classy-fashion::storefront.contact')],
    ];
    $displayColumns = $columns->take(3);
    if ($displayColumns->count() < 3) {
        $displayColumns->push($accountLinks);
    }
    $policyLinks = $columns->flatten(1);
    $privacyLink = $policyLinks->first(fn ($link) => str_contains($link['url'] ?? '', 'privacy-policy'));
    $termsLink = $policyLinks->first(fn ($link) => str_contains($link['url'] ?? '', 'terms-conditions'));
@endphp

<footer
    class="cfh-footer"
    @if ($section && $sectionRepository->isPreviewing())
        data-section-id="{{ $section->id }}"
        data-section-name="{{ $section->name }}"
    @endif
>
    <div class="container">
        <div class="cfh-footer-main">
            <div class="cfh-footer-brand">
                <a href="{{ route('shop.home.index') }}" aria-label="{{ config('app.name') }}">
                    <img src="{{ asset('images/brand-logo.png') }}" width="164" height="55" alt="{{ config('app.name') }}" loading="lazy">
                </a>
                <p>{{ trans('classy-fashion::storefront.footer_description') }}</p>
                <a class="cfh-footer-contact" href="{{ route('shop.home.contact_us') }}">{{ trans('classy-fashion::storefront.contact') }}</a>
            </div>
            <div class="cfh-footer-links" v-pre>
                @foreach ($displayColumns as $links)
                    <ul>
                        @foreach ($links as $link)
                            <li><a href="{{ $link['url'] }}">{{ $link['title'] }}</a></li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
    </div>
    <div class="container cfh-footer-bottom">
        {!! view_render_event('bagisto.shop.layout.footer.footer_text.before') !!}
        <p>
            @if (core()->getConfigData('general.content.footer.copyright_content'))
                {!! clean_content((string) core()->getConfigData('general.content.footer.copyright_content')) !!}
            @else
                @lang('shop::app.components.layouts.footer.footer-text', ['current_year' => date('Y')])
            @endif
        </p>
        <div class="cfh-footer-policies">
            @if ($termsLink)<a href="{{ $termsLink['url'] }}">{{ trans('classy-fashion::storefront.footer_terms') }}</a>@endif
            @if ($privacyLink)<a href="{{ $privacyLink['url'] }}">{{ trans('classy-fashion::storefront.footer_privacy') }}</a>@endif
        </div>
        <div class="cfh-footer-currency">{{ trans('classy-fashion::storefront.currency') }}</div>
        {!! view_render_event('bagisto.shop.layout.footer.footer_text.after') !!}
    </div>
</footer>

{!! view_render_event('bagisto.shop.layout.footer.after') !!}
