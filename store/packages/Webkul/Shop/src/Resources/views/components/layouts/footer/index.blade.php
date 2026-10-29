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
        ->map(fn ($links) => array_filter((array) $links, fn ($link) => filled($link['title'] ?? null)))
        ->filter();
@endphp

<footer
    class="cfh-footer"
    @if ($section && $sectionRepository->isPreviewing())
        data-section-id="{{ $section->id }}"
        data-section-name="{{ $section->name }}"
    @endif
>
    <div class="cfh-footer__top">
        <!-- Brand + promise -->
        <div class="cfh-footer__brand">
            <a href="{{ route('shop.home.index') }}" aria-label="Classy Fashion Hub">
                <img
                    src="{{ asset('images/brand-logo.png') }}"
                    alt="Classy Fashion Hub"
                    width="160"
                    height="36"
                    loading="lazy"
                >
            </a>

            <p>
                Everyday and occasion wear for students and professionals across Uganda.
                Fixed prices in UGX, no bargaining.
            </p>

            <ul class="cfh-pay" aria-label="Payment methods">
                <li>MTN MoMo</li>
                <li>Airtel Money</li>
                <li>Cash on delivery</li>
            </ul>
        </div>

        <!-- Link columns (managed in admin) -->
        <nav class="cfh-footer__links" aria-label="Footer" v-pre>
            @foreach ($columns as $footerLinkSection)
                <ul>
                    @foreach ($footerLinkSection as $link)
                        <li>
                            <a href="{{ $link['url'] }}">{{ $link['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        {!! view_render_event('bagisto.shop.layout.footer.newsletter_subscription.before') !!}

        <!-- Newsletter -->
        @if (core()->getConfigData('customer.settings.newsletter.subscription'))
            <div class="cfh-footer__news">
                <p class="cfh-eyebrow">Newsletter</p>

                <p class="cfh-footer__news-title">
                    @lang('shop::app.components.layouts.footer.newsletter-text')
                </p>

                <x-shop::form
                    :action="route('shop.subscription.store')"
                    class="cfh-news-form"
                    toolname="subscribe_to_newsletter"
                    tooldescription="{{ trans('shop::app.components.layouts.webmcp.subscribe-newsletter') }}"
                    toolautosubmit
                >
                    <div class="cfh-news-form__row">
                        <x-shop::form.control-group.control
                            type="email"
                            name="email"
                            rules="required|email"
                            label="Email"
                            :aria-label="trans('shop::app.components.layouts.footer.email')"
                            placeholder="email@example.com"
                            toolparamdescription="{{ trans('shop::app.components.layouts.webmcp.subscribe-newsletter-email') }}"
                        />

                        <button type="submit">
                            @lang('shop::app.components.layouts.footer.subscribe')
                        </button>
                    </div>

                    <x-shop::form.control-group.error control-name="email" />
                </x-shop::form>
            </div>
        @endif

        {!! view_render_event('bagisto.shop.layout.footer.newsletter_subscription.after') !!}
    </div>

    <div class="cfh-footer__bottom">
        {!! view_render_event('bagisto.shop.layout.footer.footer_text.before') !!}

        <p>
            @if (core()->getConfigData('general.content.footer.copyright_content'))
                {!! clean_content((string) core()->getConfigData('general.content.footer.copyright_content')) !!}
            @else
                @lang('shop::app.components.layouts.footer.footer-text', ['current_year'=> date('Y') ])
            @endif
        </p>

        <p class="cfh-thanks">Webale nnyo. Thank you for shopping with us.</p>

        {!! view_render_event('bagisto.shop.layout.footer.footer_text.after') !!}
    </div>
</footer>

{!! view_render_event('bagisto.shop.layout.footer.after') !!}
