<?php

return [
    'orders' => [
        'mark_as' => 'Mark as :status',
        'delivery_instructions' => 'Delivery Instructions',
        'profit' => 'Profit: :amount',
        'order_profit' => 'Order Profit',
        'partner_save' => 'Save Rider',
        'partner_placeholder' => 'Rider name or company (optional)',
        'partner_saved' => 'Delivery partner saved.',
    ],

    'checkout' => [
        'delivery_instructions' => 'Delivery Instructions (e.g. gate, landmark, call on arrival)',
        'delivery_instructions_placeholder' => 'e.g. Blue gate opposite the mosque, call on arrival',
    ],

    'configuration' => [
        'mobile_money' => 'Mobile Money (MTN / Airtel)',
        'mobile_money_info' => 'Flutterwave sandbox/test keys live ONLY in .env (FLUTTERWAVE_*). Nothing secret is stored here.',
        'sandbox' => 'Sandbox Mode (use Flutterwave test keys)',
    ],

    'mobilemoney' => [
        'title' => 'Mobile Money Payment',
        'choose_network' => 'Pay :amount from your phone. Choose your network and enter the mobile money number.',
        'network' => 'Network',
        'phone' => 'Mobile Money Number',
        'pay_now' => 'Send Payment Prompt',
        'approve_title' => 'Approve on Your Phone',
        'approve_body' => 'A payment prompt of :amount was sent to your :network line. Enter your mobile money PIN on the phone to approve.',
        'tx_ref' => 'Reference: :ref',
        'check_status' => 'I Have Approved — Check Status',
        'ugx_only' => 'Mobile money is available for UGX orders only.',
        'charge_failed' => 'The payment could not be started. Please try again or choose another method.',
        'payment_failed' => 'The payment failed or was cancelled. No order was created; your cart is unchanged.',
    ],

    'menu' => [
        'profit_report' => 'Profit Report',
    ],

    'assistant' => [
        'title' => 'Shop Assistant',
        'subtitle' => 'Ask about products, prices, sizes, delivery, payments or your order.',
        'greeting' => 'Hello, and welcome to Classy Fashion Hub! Try “red dresses under 100000” or “where is my order?”.',
        'placeholder' => 'Type your question…',
        'send' => 'Send',
    ],

    'reports' => [
        'profit_title' => 'Profit Report',
        'start' => 'Start Date',
        'end' => 'End Date',
        'apply' => 'Apply',
        'export_csv' => 'Export CSV',
        'total_sales' => 'Total Sales',
        'transactions' => 'Transactions',
        'total_profit' => 'Total Profit',
        'date' => 'Date',
        'no_data' => 'No orders in this period.',
    ],
];
