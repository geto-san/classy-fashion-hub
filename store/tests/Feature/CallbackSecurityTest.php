<?php

use Illuminate\Session\TokenMismatchException;

use function Pest\Laravel\postJson;

it('accepts an MTN server callback without a browser CSRF token in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    postJson(route('classy.mobilemoney.mtn-callback'), ['externalId' => 'unknown-migration-reference'])
        ->assertOk()
        ->assertJsonPath('message', 'Nothing to do.');
});

it('still requires browser CSRF protection for customer payment actions', function () {
    $this->app->detectEnvironment(fn () => 'production');
    $this->withoutExceptionHandling();
    $this->expectException(TokenMismatchException::class);

    postJson(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '0772000002']);
});
