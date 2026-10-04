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
        'mobile_money_info' => 'Flutterwave keys and the sandbox/live switch live ONLY in the server environment (FLUTTERWAVE_*, FLUTTERWAVE_SANDBOX). Nothing secret is stored here.',
    ],

    'mobilemoney' => [
        'title' => 'Mobile Money Payment',
        'claim_saved' => 'Reference saved. We shall confirm your payment and create your order.',
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
        'phone_invalid' => 'Enter a Ugandan mobile money number, e.g. 0772000000 or +256772000000.',
        'payment_expired' => 'The payment prompt timed out. If you were charged, contact us with reference :ref; otherwise try again.',
        'payment_unfulfilled' => 'We received your payment but could not finish your order. We have been alerted: please contact us with reference :ref and do not pay again.',
        'payment_failed' => 'The payment failed or was cancelled. No order was created; your cart is unchanged.',
        'till_title' => 'Or pay to our Till by phone',
        'till_body' => 'Send :amount to Till :till from your :network line, then enter the transaction ID from your SMS below.',
        'till_ref' => 'Your MTN/Airtel transaction ID',
        'till_save' => 'I Have Paid — Confirm',
    ],

    'timeline' => [
        'title' => 'Order progress',
        'placed' => 'Order placed',
    ],

    'menu' => [
        'profit_report' => 'Profit Report',
        'audit_log' => 'Audit Log',
        'payments' => 'Mobile Money Payments',
    ],

    'audit' => [
        'title' => 'Audit Log',
        'event' => 'Event',
        'who' => 'Who',
        'what' => 'What happened',
        'all' => 'All',
        'system' => 'System',
        'none' => 'No entries match.',
    ],

    'payments' => [
        'title' => 'Mobile Money Payments',
        'status' => 'Status',
        'reference' => 'Reference',
        'provider' => 'Provider',
        'amount' => 'Amount',
        'order' => 'Order',
        'note' => 'Note',
        'claim' => 'Customer Claim',
        'confirm' => 'Confirm Paid',
        'confirmed' => 'Payment confirmed; the paid order was created.',
        'confirm_failed' => 'Could not confirm: the attempt has no customer claim or is no longer open.',
        'none' => 'No payment attempts yet.',
        'attention' => ':count payment(s) need attention: money was received but no order exists (or finalising did not finish). Create the order by hand or refund the customer, quoting the reference.',
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
