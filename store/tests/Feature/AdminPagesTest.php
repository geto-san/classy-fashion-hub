<?php

use ClassyFashion\Listeners\AuditStaffChanges;
use ClassyFashion\Models\PaymentAttempt;
use Database\Seeders\ClassyFashionSeeder;
use Spatie\Activitylog\Models\Activity;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;

function pagesOwner(): Admin
{
    return Admin::where('email', 'admin@example.com')->firstOrFail();
}

it('shows the audit log to the owner and exports it as CSV', function () {
    $this->seed(ClassyFashionSeeder::class);

    $this->actingAs(pagesOwner(), 'admin');

    app(AuditStaffChanges::class)->adminDeleted(4242);

    get(route('admin.classy.reports.audit'))
        ->assertOk()
        ->assertSee('Audit Log', false)
        ->assertSee('Staff account #4242 deleted', false);

    get(route('admin.classy.reports.audit', ['event' => 'staff.deleted']))
        ->assertOk()
        ->assertSee('Staff account #4242 deleted', false);

    $csv = get(route('admin.classy.reports.audit', ['export' => 'csv']))->assertOk();

    expect($csv->streamedContent())->toContain('When,Who,Event,What');
});

it('keeps the audit log and the payments list away from the worker', function () {
    $this->seed(ClassyFashionSeeder::class);

    $this->actingAs(Admin::where('email', 'worker@classy.local')->firstOrFail(), 'admin');

    get(route('admin.classy.reports.audit'))->assertUnauthorized();
    get(route('admin.classy.payments.index'))->assertUnauthorized();
});

it('lists payment attempts and warns about paid-but-unfulfilled ones', function () {
    $this->actingAs(pagesOwner(), 'admin');

    PaymentAttempt::create([
        'cart_id' => 1, 'tx_ref' => 'CFH-NEEDS-HELP-1', 'amount' => 45000, 'currency' => 'UGX',
        'network' => 'MTN', 'status' => PaymentAttempt::STATUS_PAID_UNFULFILLED,
        'failure_reason' => 'Order creation failed after payment.',
    ]);

    get(route('admin.classy.payments.index'))
        ->assertOk()
        ->assertSee('CFH-NEEDS-HELP-1', false)
        ->assertSee('need attention', false);

    get(route('admin.classy.payments.index', ['status' => 'success']))
        ->assertOk()
        ->assertDontSee('CFH-NEEDS-HELP-1', false);
});

it('writes staff account and role changes to the audit log without passwords', function () {
    $this->actingAs(pagesOwner(), 'admin');

    $admin = pagesOwner();

    app(AuditStaffChanges::class)->adminCreated($admin);

    $entry = Activity::where('log_name', 'classy-fashion')->where('event', 'staff.created')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe($admin->id)
        ->and(json_encode($entry->properties))->not->toContain('password');
});
