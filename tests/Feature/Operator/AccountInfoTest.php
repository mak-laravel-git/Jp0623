<?php

use App\Enums\RoleEnum;
use App\Mail\AccountEmailOtpMail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    config(['services.twilio.enabled' => false]);
    Mail::fake();
});

function useTwilio(int $status = 201): void
{
    config([
        'services.twilio.enabled' => true,
        'services.twilio.sid' => 'AC123',
        'services.twilio.token' => 'twilio-token',
        'services.twilio.from' => '+15550001111',
        'services.twilio.messaging_service_sid' => null,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'SM1', 'status' => 'queued'], $status),
        '*__inertia_ssr*' => Http::response('', 404),
    ]);
}

/**
 * Ask for an email code as the member and return the code that was mailed.
 */
function requestEmailCode(User $member, string $email): string
{
    test()->actingAs($member)
        ->post(route('account.email.code'), ['email' => $email, 'current_password' => 'password'])
        ->assertSessionHasNoErrors();

    return Mail::sent(AccountEmailOtpMail::class)->last()->code;
}

/**
 * Ask for a text code as the member and return the code that was texted.
 */
function requestPhoneCode(User $member, string $phone): string
{
    test()->actingAs($member)
        ->post(route('account.phone.code'), ['phone' => $phone])
        ->assertSessionHasNoErrors();

    [$request] = Http::recorded(fn ($request) => str_contains($request->url(), 'api.twilio.com'))->last();

    preg_match('/\b(\d{6})\b/', $request['Body'], $matches);

    return $matches[1];
}

function wrongCode(string $code): string
{
    return $code === '000000' ? '111111' : '000000';
}

test('guests cannot change any account detail', function (string $method, string $route) {
    $this->{$method}(route($route))->assertRedirect(route('login'));
})->with([
    ['put', 'account.update'],
    ['post', 'account.email.code'],
    ['put', 'account.email.update'],
    ['post', 'account.phone.code'],
    ['put', 'account.phone.update'],
    ['get', 'account.two-factor'],
]);

test('the page shows what the member saved', function () {
    $member = makeOperator(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'company_name' => 'Rahim Vending', 'phone' => '(713) 555-1234']);

    $this->actingAs($member)
        ->get(route('account.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/account/index')
            ->where('account.firstName', 'Rahim')
            ->where('account.lastName', 'Uddin')
            ->where('account.company', 'Rahim Vending')
            ->where('account.phone', '(713) 555-1234')
            ->where('account.hasProfile', true)
            ->where('smsVerification', false));
});

test('missing details stay empty instead of showing made up ones', function () {
    $member = User::factory()->create(['name' => 'Rahim']);
    $member->assignRole(RoleEnum::USER->value);

    $this->actingAs($member)
        ->get(route('account.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('account.firstName', 'Rahim')
            ->where('account.lastName', '')
            ->where('account.company', '')
            ->where('account.phone', '')
            ->where('account.hasProfile', false));
});

test('a member saves their name and company', function () {
    $member = makeOperator();

    $this->actingAs($member)
        ->put(route('account.update'), ['first_name' => 'Karim', 'last_name' => 'Khan', 'company_name' => 'Karim Snacks'])
        ->assertSessionHasNoErrors();

    expect($member->refresh()->name)->toBe('Karim Khan')
        ->and($member->operatorProfile->first_name)->toBe('Karim')
        ->and($member->operatorProfile->last_name)->toBe('Khan')
        ->and($member->operatorProfile->company_name)->toBe('Karim Snacks');
});

test('the profile needs a first name, last name and company', function () {
    $member = makeOperator(['company_name' => 'Kept Co']);

    $this->actingAs($member)
        ->put(route('account.update'), [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'company_name']);

    expect($member->operatorProfile->refresh()->company_name)->toBe('Kept Co');
});

test('a member without a company profile can still change their name', function () {
    $member = User::factory()->create();
    $member->assignRole(RoleEnum::USER->value);

    $this->actingAs($member)
        ->put(route('account.update'), ['first_name' => 'Karim', 'last_name' => 'Khan'])
        ->assertSessionHasNoErrors();

    expect($member->refresh()->name)->toBe('Karim Khan')
        ->and($member->operatorProfile()->exists())->toBeFalse();
});

test('an email code is mailed to the new address and nothing changes yet', function () {
    $member = makeOperator();
    $oldEmail = $member->email;

    $this->actingAs($member)
        ->post(route('account.email.code'), ['email' => '  New@Example.com ', 'current_password' => 'password'])
        ->assertSessionHasNoErrors();

    Mail::assertSent(AccountEmailOtpMail::class, fn (AccountEmailOtpMail $mail) => $mail->hasTo('new@example.com'));
    expect($member->refresh()->email)->toBe($oldEmail);
});

test('an email code is refused for a wrong password or an unusable address', function (array $payload, string $field) {
    User::factory()->create(['email' => 'taken@example.com']);
    $member = User::factory()->create(['email' => 'mine@example.com']);

    $this->actingAs($member)
        ->post(route('account.email.code'), $payload + ['email' => 'new@example.com', 'current_password' => 'password'])
        ->assertSessionHasErrors($field);

    Mail::assertNothingSent();
})->with([
    'wrong password' => [['current_password' => 'nope'], 'current_password'],
    'another account' => [['email' => 'taken@example.com'], 'email'],
    'own address' => [['email' => 'MINE@example.com'], 'email'],
    'not an email' => [['email' => 'nope'], 'email'],
]);

test('the right code changes the email, verifies it and kills old reset links', function () {
    $member = makeOperator();
    Password::broker()->createToken($member);

    $code = requestEmailCode($member, 'new@example.com');

    $this->actingAs($member)
        ->put(route('account.email.update'), ['email' => 'new@example.com', 'code' => $code])
        ->assertSessionHasNoErrors();

    expect($member->refresh()->email)->toBe('new@example.com')
        ->and($member->email_verified_at)->not->toBeNull()
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('an email code only works for the address it was sent to', function () {
    $member = makeOperator();
    $code = requestEmailCode($member, 'new@example.com');

    $this->actingAs($member)
        ->put(route('account.email.update'), ['email' => 'other@example.com', 'code' => $code])
        ->assertSessionHasErrors('code');

    expect($member->refresh()->email)->not->toBe('other@example.com');
});

test('an email code expires after ten minutes', function () {
    $member = makeOperator();
    $code = requestEmailCode($member, 'new@example.com');

    $this->travel(11)->minutes();

    $this->actingAs($member)
        ->put(route('account.email.update'), ['email' => 'new@example.com', 'code' => $code])
        ->assertSessionHasErrors('code');
});

test('five wrong tries throw the email code away', function () {
    $member = makeOperator();
    $code = requestEmailCode($member, 'new@example.com');

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($member)
            ->put(route('account.email.update'), ['email' => 'new@example.com', 'code' => wrongCode($code)])
            ->assertSessionHasErrors('code');
    }

    $this->actingAs($member)
        ->put(route('account.email.update'), ['email' => 'new@example.com', 'code' => $code])
        ->assertSessionHasErrors('code');

    expect($member->refresh()->email)->not->toBe('new@example.com');
});

test('asking for email codes is rate limited', function () {
    $member = makeOperator();

    foreach (range(1, 5) as $attempt) {
        requestEmailCode($member, 'new@example.com');
    }

    $this->actingAs($member)
        ->post(route('account.email.code'), ['email' => 'new@example.com', 'current_password' => 'password'])
        ->assertTooManyRequests();
});

test('without Twilio the phone is saved after the current password', function () {
    $member = makeOperator(['phone' => '7135550000']);

    $this->actingAs($member)
        ->put(route('account.phone.update'), ['phone' => '(713) 555-9999', 'current_password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($member->operatorProfile->refresh()->phone)->toBe('(713) 555-9999');
});

test('without Twilio a wrong password or bad number keeps the old phone', function (array $payload, string $field) {
    $member = makeOperator(['phone' => '7135550000']);

    $this->actingAs($member)
        ->put(route('account.phone.update'), $payload + ['phone' => '7135559999', 'current_password' => 'password'])
        ->assertSessionHasErrors($field);

    expect($member->operatorProfile->refresh()->phone)->toBe('7135550000');
})->with([
    'wrong password' => [['current_password' => 'nope'], 'current_password'],
    'letters' => [['phone' => 'call me'], 'phone'],
    'too short' => [['phone' => '12345'], 'phone'],
]);

test('without Twilio no code can be requested', function () {
    $this->actingAs(makeOperator())
        ->post(route('account.phone.code'), ['phone' => '7135559999'])
        ->assertForbidden();
});

test('with Twilio the texted code changes the phone', function () {
    useTwilio();
    $member = makeOperator(['phone' => '7135550000']);

    $code = requestPhoneCode($member, '(713) 555-9999');

    Http::assertSent(fn ($request) => $request['To'] === '+17135559999');
    expect($member->operatorProfile->refresh()->phone)->toBe('7135550000');

    $this->actingAs($member)
        ->put(route('account.phone.update'), ['phone' => '(713) 555-9999', 'code' => $code])
        ->assertSessionHasNoErrors();

    expect($member->operatorProfile->refresh()->phone)->toBe('(713) 555-9999');
});

test('with Twilio the password alone, a wrong code or another number is not enough', function () {
    useTwilio();
    $member = makeOperator(['phone' => '7135550000']);

    $this->actingAs($member)
        ->put(route('account.phone.update'), ['phone' => '7135559999', 'current_password' => 'password'])
        ->assertSessionHasErrors('code');

    $code = requestPhoneCode($member, '7135559999');

    $this->actingAs($member)
        ->put(route('account.phone.update'), ['phone' => '7135559999', 'code' => wrongCode($code)])
        ->assertSessionHasErrors('code');

    $this->actingAs($member)
        ->put(route('account.phone.update'), ['phone' => '7135558888', 'code' => $code])
        ->assertSessionHasErrors('code');

    expect($member->operatorProfile->refresh()->phone)->toBe('7135550000');
});

test('a failed text message is reported on the phone field', function () {
    useTwilio(status: 400);

    $this->actingAs(makeOperator())
        ->post(route('account.phone.code'), ['phone' => '7135559999'])
        ->assertSessionHasErrors('phone');
});

test('a member changes their password from the account page', function () {
    $member = makeOperator();

    $this->actingAs($member)
        ->from(route('account.index'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.index'));

    expect(Hash::check('brand-new-password', $member->refresh()->password))->toBeTrue();
});

test('two factor settings stay locked until the password is confirmed', function () {
    $member = makeOperator();

    $this->actingAs($member)
        ->get(route('account.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false)
            ->where('twoFactorLocked', true));

    $this->actingAs($member)
        ->get(route('account.two-factor'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($member)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('account.index'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorLocked', false));
});
