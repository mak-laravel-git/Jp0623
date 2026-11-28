<?php

use App\Enums\RoleEnum;
use App\Models\TwilioSetting;
use App\Models\User;
use App\Services\TwilioSettingsService;
use App\Services\TwilioSmsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

const TWILIO_TEST_SID = 'AC00000000000000000000000000000001';
const TWILIO_TEST_SERVICE_SID = 'MG00000000000000000000000000000002';

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    configureTwilioEnv();

    $this->admin = User::factory()->create(['name' => 'Admin Amy']);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function configureTwilioEnv(array $overrides = []): void
{
    config()->set('services.twilio', array_replace([
        'enabled' => false,
        'sid' => null,
        'token' => null,
        'from' => null,
        'messaging_service_sid' => null,
        'sandbox_message_prefix' => '',
        'lead_radius_miles' => 100.0,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function twilioSettingsPayload(array $overrides = []): array
{
    return $overrides + [
        'enabled' => true,
        'sid' => TWILIO_TEST_SID,
        'auth_token' => 'secret-auth-token-1234',
        'from_number' => '+15005550006',
        'messaging_service_sid' => TWILIO_TEST_SERVICE_SID,
        'lead_radius_miles' => 150,
    ];
}

test('guests and members cannot see or change twilio settings', function () {
    $this->get(route('admin.twilio-settings.edit'))->assertRedirect(route('login'));

    $member = makeOperator();

    $this->actingAs($member)->get(route('admin.twilio-settings.edit'))->assertForbidden();
    $this->actingAs($member)->put(route('admin.twilio-settings.update'), twilioSettingsPayload())->assertForbidden();
    $this->actingAs($member)->post(route('admin.twilio-settings.test'))->assertForbidden();

    expect(TwilioSetting::query()->count())->toBe(0);
});

test('the page shows where each value comes from and never the auth token', function () {
    configureTwilioEnv(['token' => 'env-auth-token-ABCD']);
    app(TwilioSettingsService::class)->update(twilioSettingsPayload(['auth_token' => '', 'from_number' => '']), $this->admin);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.twilio-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/twilio-settings/edit', false)
            ->where('settings.enabled.source', 'admin')
            ->where('settings.fields.sid.value', TWILIO_TEST_SID)
            ->where('settings.fields.sid.source', 'admin')
            ->where('settings.fields.from_number.source', 'missing')
            ->where('settings.secrets.auth_token.source', 'env')
            ->where('settings.secrets.auth_token.last4', 'ABCD')
            ->where('settings.is_configured', true)
        );

    expect(json_encode($response->viewData('page')['props']))->not->toContain('env-auth-token');
});

test('the admin saves the settings and the auth token is stored encrypted', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.twilio-settings.update'), twilioSettingsPayload(['from_number' => '(832) 555-0100']))
        ->assertSessionHasNoErrors();

    $row = TwilioSetting::query()->sole();

    expect($row->enabled)->toBeTrue()
        ->and($row->sid)->toBe(TWILIO_TEST_SID)
        ->and($row->auth_token)->toBe('secret-auth-token-1234')
        ->and(DB::table('twilio_settings')->value('auth_token'))->not->toContain('secret-auth-token')
        ->and($row->from_number)->toBe('+18325550100')
        ->and($row->lead_radius_miles)->toBe(150)
        ->and($row->updated_by)->toBe($this->admin->id);
});

test('an empty auth token keeps the saved one and the clear option removes it', function () {
    app(TwilioSettingsService::class)->update(twilioSettingsPayload(), $this->admin);

    $this->actingAs($this->admin)
        ->put(route('admin.twilio-settings.update'), twilioSettingsPayload(['auth_token' => '', 'lead_radius_miles' => 200]))
        ->assertSessionHasNoErrors();

    $row = TwilioSetting::query()->sole();

    expect($row->auth_token)->toBe('secret-auth-token-1234')
        ->and($row->lead_radius_miles)->toBe(200);

    $this->actingAs($this->admin)
        ->put(route('admin.twilio-settings.update'), twilioSettingsPayload(['auth_token' => '', 'clear_auth_token' => true]));

    expect($row->refresh()->auth_token)->toBeNull();
});

test('the settings are validated', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.twilio-settings.update'), twilioSettingsPayload([
            'sid' => 'SK123',
            'messaging_service_sid' => 'AC123',
            'from_number' => 'call me',
            'lead_radius_miles' => 0,
        ]))
        ->assertSessionHasErrors(['sid', 'messaging_service_sid', 'from_number', 'lead_radius_miles']);

    expect(TwilioSetting::query()->count())->toBe(0);
});

test('saved values take priority and empty ones fall back to env', function () {
    configureTwilioEnv(['sid' => 'ACenv', 'token' => 'env-token', 'from' => '+15005550001']);

    app(TwilioSettingsService::class)->update(
        twilioSettingsPayload(['from_number' => '', 'auth_token' => '', 'messaging_service_sid' => '']),
        $this->admin,
    );

    expect(app(TwilioSettingsService::class)->current())->toMatchArray([
        'enabled' => true,
        'sid' => TWILIO_TEST_SID,
        'token' => 'env-token',
        'from' => '+15005550001',
        'lead_radius_miles' => 150,
    ]);
});

test('switching sms off here overrides an enabled env', function () {
    configureTwilioEnv(['enabled' => true, 'sid' => 'ACenv', 'token' => 'env-token', 'from' => '+15005550001']);

    app(TwilioSettingsService::class)->update(twilioSettingsPayload(['enabled' => false]), $this->admin);

    expect(app(TwilioSmsService::class)->isEnabled())->toBeFalse();
});

test('an already built sms service sends with settings saved afterwards through the messaging service', function () {
    Http::fake([
        'api.twilio.com/2010-04-01/Accounts/'.TWILIO_TEST_SID.'/Messages.json' => Http::response(['sid' => 'SM1', 'status' => 'queued'], 201),
    ]);
    $sms = app(TwilioSmsService::class);

    app(TwilioSettingsService::class)->update(twilioSettingsPayload(['from_number' => '']), $this->admin);
    $sms->send('+17135550100', 'Hello');

    Http::assertSent(fn (Request $request): bool => $request['MessagingServiceSid'] === TWILIO_TEST_SERVICE_SID
        && ! isset($request['From'])
        && $request->hasHeader('Authorization', 'Basic '.base64_encode(TWILIO_TEST_SID.':secret-auth-token-1234')));
});

test('a saved token that cannot be decrypted disables sending instead of using the env token', function () {
    configureTwilioEnv(['token' => 'env-token']);
    app(TwilioSettingsService::class)->update(twilioSettingsPayload(), $this->admin);
    DB::table('twilio_settings')->update(['auth_token' => 'not-an-encrypted-value']);

    $service = app(TwilioSettingsService::class);

    expect($service->current()['token'])->toBeNull()
        ->and($service->isReady())->toBeFalse();
});

test('test connection reports success without sending a message', function () {
    app(TwilioSettingsService::class)->update(twilioSettingsPayload(), $this->admin);
    Http::fake([
        'api.twilio.com/2010-04-01/Accounts/'.TWILIO_TEST_SID.'.json' => Http::response(['friendly_name' => 'Main account']),
        'messaging.twilio.com/v1/Services/'.TWILIO_TEST_SERVICE_SID => Http::response(['friendly_name' => 'Lead alerts']),
    ]);

    $result = app(TwilioSettingsService::class)->testConnection();

    expect($result)->toBe(['ok' => true, 'message' => 'Connected to Twilio account "Main account" and Messaging Service "Lead alerts".']);
    Http::assertSentCount(2);
});

test('test connection reports rejected credentials', function () {
    app(TwilioSettingsService::class)->update(twilioSettingsPayload(), $this->admin);
    Http::fake([
        'api.twilio.com/2010-04-01/Accounts/'.TWILIO_TEST_SID.'.json' => Http::response(['message' => 'Authenticate'], 401),
    ]);

    $result = app(TwilioSettingsService::class)->testConnection();

    expect($result)->toBe(['ok' => false, 'message' => 'Twilio rejected the credentials: Authenticate.']);
});

test('test connection says so when twilio is not configured', function () {
    $result = app(TwilioSettingsService::class)->testConnection();

    expect($result['ok'])->toBeFalse()->and($result['message'])->toContain('not configured');
});
