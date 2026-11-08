@php
    use ClassyFashion\Support\Storefront;
@endphp

<section class="cfh-african-campaign" data-african-campaign aria-labelledby="campaign-title">
    <div class="cfh-african-campaign-copy">
        <h1 id="campaign-title">{{ Storefront::get('hero_title') }}</h1>
        <p>{{ Storefront::get('hero_text') }}</p>
        <a class="cfh-button" href="{{ Storefront::get('hero_link') }}">{{ Storefront::get('hero_button') }}</a>
    </div>

    <div class="cfh-african-campaign-stage">
        @foreach ($slides as $slide)
            <figure class="cfh-african-campaign-slide @if ($loop->first) is-active @endif" data-campaign-slide aria-hidden="{{ $loop->first ? 'false' : 'true' }}" @if (! $loop->first) inert @endif>
                <a href="{{ $slide['link'] }}" tabindex="{{ $loop->first ? '0' : '-1' }}">
                    <img src="{{ $slide['src'] }}" width="1200" height="1200" alt="{{ $slide['name'] }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" @if ($loop->first) fetchpriority="high" @endif>
                    <figcaption>{{ $slide['name'] }}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></figcaption>
                </a>
            </figure>
        @endforeach
    </div>

    <div class="cfh-african-campaign-controls">
        <span class="cfh-african-campaign-count" aria-live="polite" aria-atomic="true"><span data-campaign-current>1</span> / {{ count($slides) }}</span>
        <button type="button" data-campaign-direction="-1" aria-label="{{ trans('shop::app.components.carousel.previous') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M19 12H5m5-5-5 5 5 5"/></svg></button>
        <button type="button" data-campaign-direction="1" aria-label="{{ trans('shop::app.components.carousel.next') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></button>
    </div>
</section>

@push('scripts')
    <script type="module">
        const showCampaignSlide = (campaign, direction) => {
            const slides = [...campaign.querySelectorAll('[data-campaign-slide]')];
            const counter = campaign.querySelector('[data-campaign-current]');
            const current = slides.findIndex(slide => slide.classList.contains('is-active'));
            const next = (current + direction + slides.length) % slides.length;
            slides.forEach((slide, index) => {
                const active = index === next;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', String(!active));
                slide.inert = !active;
                slide.querySelector('a').tabIndex = active ? 0 : -1;
            });
            counter.textContent = String(next + 1);
        };

        document.addEventListener('click', event => {
            const button = event.target.closest('[data-campaign-direction]');
            if (button) showCampaignSlide(button.closest('[data-african-campaign]'), Number(button.dataset.campaignDirection));
        });

        document.addEventListener('keydown', event => {
            const campaign = event.target.closest('[data-african-campaign]');
            if (!campaign || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            showCampaignSlide(campaign, event.key === 'ArrowLeft' ? -1 : 1);
        });
    </script>
@endpush
