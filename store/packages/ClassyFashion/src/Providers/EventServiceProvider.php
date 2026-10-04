<?php

namespace ClassyFashion\Providers;

use ClassyFashion\Listeners\AuditStaffChanges;
use ClassyFashion\Listeners\CheckoutDeliveryField;
use ClassyFashion\Listeners\OrderDeliveryInfo;
use ClassyFashion\Listeners\OrderPaymentRecords;
use ClassyFashion\Listeners\OrderProfitSummary;
use ClassyFashion\Listeners\OrderStatusButtons;
use ClassyFashion\Listeners\OrderStatusMapper;
use ClassyFashion\Listeners\SnapshotOrderItemCost;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        'sales.order.update-status.after' => [
            [OrderStatusMapper::class, 'mapCompletedToDispatched'],
        ],

        'user.admin.create.after' => [[AuditStaffChanges::class, 'adminCreated']],
        'user.admin.update.after' => [[AuditStaffChanges::class, 'adminUpdated']],
        'user.admin.delete.after' => [[AuditStaffChanges::class, 'adminDeleted']],
        'user.role.create.after'  => [[AuditStaffChanges::class, 'roleCreated']],
        'user.role.update.after'  => [[AuditStaffChanges::class, 'roleUpdated']],
        'user.role.delete.after'  => [[AuditStaffChanges::class, 'roleDeleted']],

        'checkout.order.orderitem.save.after' => [
            [SnapshotOrderItemCost::class, 'handle'],
        ],

        'bagisto.admin.sales.order.status_label.after' => [
            [OrderStatusButtons::class, 'addButtons'],
            [OrderPaymentRecords::class, 'show'],
        ],

        'bagisto.admin.sales.order.billing_address.after' => [
            [OrderDeliveryInfo::class, 'showBillingInfo'],
        ],

        'bagisto.admin.sales.order.shipping_address.after' => [
            [OrderDeliveryInfo::class, 'showShippingInfo'],
        ],

        'bagisto.admin.sales.order.list.item.after' => [
            [OrderProfitSummary::class, 'showItemProfit'],
        ],

        'bagisto.admin.sales.order.view.grand-total.after' => [
            [OrderProfitSummary::class, 'showOrderProfit'],
        ],

        'bagisto.shop.checkout.onepage.address.form.phone.after' => [
            [CheckoutDeliveryField::class, 'addField'],
        ],
    ];
}
