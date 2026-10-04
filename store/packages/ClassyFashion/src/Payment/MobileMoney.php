<?php

namespace ClassyFashion\Payment;

use ClassyFashion\Support\Flutterwave;
use Webkul\Payment\Payment\Payment;

/**
 * Mobile Money via Flutterwave (MTN / Airtel Uganda, report 9.6).
 *
 * Redirect-style method: the order is created only after server-side
 * verification. Gateway keys live exclusively in environment variables
 * (documented in .env.example) and are read through
 * config('classy.flutterwave'); the admin screen carries display
 * settings only.
 */
class MobileMoney extends Payment
{
    protected $code = 'mobilemoney';

    public function isAvailable()
    {
        if (! parent::isAvailable()) {
            return false;
        }

        if (! $this->cart) {
            $this->setCart();
        }

        if ($this->cart?->cart_currency_code !== 'UGX') {
            return false;
        }

        // Offered when any API provider is configured, or when the shop
        // runs the manual Till flow (no signup needed).
        return \ClassyFashion\Payment\Momo::provider() !== null
            || \ClassyFashion\Payment\Momo::manualEnabled();
    }

    public function getRedirectUrl()
    {
        return route('classy.mobilemoney.redirect');
    }

    public function getImage()
    {
        return url('images/mobile-money.png');
    }
}
