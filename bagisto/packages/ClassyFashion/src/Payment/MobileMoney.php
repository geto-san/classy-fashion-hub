<?php

namespace ClassyFashion\Payment;

use Webkul\Payment\Payment\Payment;

/**
 * Mobile Money via Flutterwave (MTN / Airtel Uganda, report 9.6).
 *
 * Redirect-style method: the order is created only after server-side
 * verification. Gateway keys live exclusively in .env (see .env.example);
 * the admin screen carries display settings only.
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

        return $this->cart?->cart_currency_code === 'UGX'
            && filled(env('FLUTTERWAVE_SECRET_KEY'))
            && filled(env('FLUTTERWAVE_PUBLIC_KEY'));
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
