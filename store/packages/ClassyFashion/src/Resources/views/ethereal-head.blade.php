<link rel="preload" href="{{ asset('fonts/work-sans/latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('css/classy-ethereal.css') }}?v={{ filemtime(public_path('css/classy-ethereal.css')) }}">
<link rel="stylesheet" href="{{ asset('css/classy-ethereal-footer.css') }}?v={{ filemtime(public_path('css/classy-ethereal-footer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/classy-ethereal-product.css') }}?v={{ filemtime(public_path('css/classy-ethereal-product.css')) }}">
<script type="module">
    document.addEventListener('keydown', (event) => {
        const target = event.target;
        if (event.key === 'Escape' && target.closest('details.cfh-header-search[open]')) {
            const search = target.closest('details.cfh-header-search');
            search.removeAttribute('open');
            search.querySelector('summary').focus();
            return;
        }

        if (event.defaultPrevented || ! ['Enter', ' '].includes(event.key)
            || ! target.matches('[role="button"][tabindex]')
            || target.matches('button, a, input, select, textarea')
            || target.getAttribute('aria-disabled') === 'true') {
            return;
        }

        event.preventDefault();
        target.click();
    });
</script>
