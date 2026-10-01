<?php

use ClassyFashion\Payment\MobileMoney;

return [
    'mobilemoney' => [
        'class'       => MobileMoney::class,
        'code'        => 'mobilemoney',
        'title'       => 'Mobile Money (MTN / Airtel)',
        'description' => 'Pay with MTN Mobile Money or Airtel Money via Flutterwave.',
        'active'      => true,
        'sort'        => 3,
    ],
];
