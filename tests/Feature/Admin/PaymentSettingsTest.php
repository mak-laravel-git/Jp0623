<?php

use App\Enums\RoleEnum;
use App\Models\SquareSetting;
use App\Models\User;
use App\Services\Payments\SquareSettingsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    fakeSquareHttp([]);
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->admin = User::factory()->create(['name' => 'Admin Amy']);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
});

function paymentSettingsPayload(array $overrides = []): array
{
    return $overrides + [
        'environment' => 'sandbox',
        'application_id' => 'sandbox-sq0idb-new',
        'location_id' => 'LOCNEW',
        'webhook_url' => '',
        'access_token' => 'EAAA-secret-token-1234',
        'webhook_signature_key' => 'sig-secret-key-5678',
    ];
}

test('guests and members cannot see or change payment settings', function () {
    $this->get(route('admin.payment-settings.edit'))->assertRedirect(route('login'));

    $member = makeOperator();

    $this->actingAs($member)->get(route('admin.payment-settings.edit'))->assertForbidden();
    $this->actingAs($member)->put(route('admin.payment-settings.update'), paymentSettingsPayload())->assertForbidden();
    $this->actingAs($member)->post(route('admin.payment-settings.test'))->assertForbidden();

    expect(SquareSetting::query()->count())->toBe(0);
});

test('the page shows where each value comes from and never the secrets', function () {
    configureSquare(['access_token' => 'ENV-TOKEN-ABCD']);
    app()->forgetInstance(SquareSettingsService::class);
    app(SquareSettingsService::class)->update(paymentSettingsPayload(['access_token' => '']), $this->admin);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.payment-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payment-settings/edit')
            ->where('settings.fields.application_id.source', 'admin')
            ->where('settings.fields.application_id.value', 'sandbox-sq0idb-new')
            ->where('settings.secrets.access_token.source', 'env')
            ->where('settings.secrets.access_token.last4', 'ABCD')
            ->where('settings.secrets.webhook_signature_key.source', 'admin')
            ->where('settings.secrets.webhook_signature_key.last4', '5678')
        );

    $props = json_encode($response->viewData('page')['props']);

    expect($props)->not->toContain('ENV-TOKEN-ABCD')->and($props)->not->toContain('sig-secret-key-5678');
});

test('the admin saves the settings and the secrets are stored encrypted', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.payment-settings.update'), paymentSettingsPayload(['environment' => 'production']))
        ->assertSessionHasNoErrors();

    $row = SquareSetting::query()->sole();
    $raw = DB::table('square_settings')->first();

    expect($row->environment)->toBe('production')
        ->and($row->application_id)->toBe('sandbox-sq0idb-new')
        ->and($row->access_token)->toBe('EAAA-secret-token-1234')
        ->and($raw->access_token)->not->toContain('secret-token')
        ->and($raw->webhook_signature_key)->not->toContain('secret-key')
        ->and($row->updated_by)->toBe($this->admin->id);
});

test('an empty secret keeps the saved one and the clear option removes it', function () {
    $service = app(SquareSettingsService::class);
    $service->update(paymentSettingsPayload(), $this->admin);

    $this->actingAs($this->admin)
        ->put(route('admin.payment-settings.update'), paymentSettingsPayload(['access_token' => '', 'webhook_signature_key' => '', 'location_id' => 'LOCCHANGED']))
        ->assertSessionHasNoErrors();

    $row = SquareSetting::query()->sole();

    expect($row->access_token)->toBe('EAAA-secret-token-1234')
        ->and($row->webhook_signature_key)->toBe('sig-secret-key-5678')
        ->and($row->location_id)->toBe('LOCCHANGED');

    $this->actingAs($this->admin)
        ->put(route('admin.payment-settings.update'), paymentSettingsPayload(['access_token' => '', 'webhook_signature_key' => '', 'clear_access_token' => true]));

    expect($row->refresh()->access_token)->toBeNull()
        ->and($row->webhook_signature_key)->toBe('sig-secret-key-5678');
});

test('the settings are validated', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.payment-settings.update'), paymentSettingsPayload(['environment' => 'staging', 'webhook_url' => 'not a url']))
        ->assertSessionHasErrors(['environment', 'webhook_url']);

    expect(SquareSetting::query()->count())->toBe(0);
});

test('saved values are laid over the env config for the app', function () {
    configureSquare(['application_id' => 'env-app', 'location_id' => 'ENVLOC', 'access_token' => 'env-token']);

    $service = app(SquareSettingsService::class);
    $service->update(paymentSettingsPayload([
        'environment' => 'production',
        'location_id' => '',
        'access_token' => 'saved-token',
        'webhook_url' => 'https://shop.test/webhooks/square',
    ]), $this->admin);
    $service->apply();

    expect(config('services.square.application_id'))->toBe('sandbox-sq0idb-new')
        ->and(config('services.square.location_id'))->toBe('ENVLOC')
        ->and(config('services.square.access_token'))->toBe('saved-token')
        ->and(config('services.square.webhook_url'))->toBe('https://shop.test/webhooks/square')
        ->and(config('services.square.base_url'))->toBe('https://connect.squareup.com')
        ->and(config('services.square.sdk_url'))->toBe('https://web.squarecdn.com/v1/square.js');
});

test('with nothing saved the env config is untouched', function () {
    configureSquare(['application_id' => 'env-app']);

    app(SquareSettingsService::class)->apply();

    expect(config('services.square.application_id'))->toBe('env-app')
        ->and(config('services.square.base_url'))->toBe('https://connect.squareupsandbox.com');
});

test('test connection reports success and Square errors', function () {
    configureSquare();

    Http::fake([
        '*__inertia_ssr*' => Http::response('', 404),
        'connect.squareupsandbox.com/v2/locations/LOC123' => Http::sequence()
            ->push(['location' => ['id' => 'LOC123', 'name' => 'Main Shop', 'status' => 'ACTIVE']])
            ->push(['errors' => [['category' => 'AUTHENTICATION_ERROR', 'code' => 'UNAUTHORIZED', 'detail' => 'The access token is wrong']]], 401),
    ]);

    $service = app(SquareSettingsService::class);

    $success = $service->testConnection();
    $failure = $service->testConnection();

    expect($success)->toBe(['ok' => true, 'message' => 'Connected to Square location "Main Shop" (active).'])
        ->and($failure['ok'])->toBeFalse()
        ->and($failure['message'])->toContain('The access token is wrong');
});

test('test connection says so when Square is not configured', function () {
    configureSquare(['access_token' => '']);

    $result = app(SquareSettingsService::class)->testConnection();

    expect($result['ok'])->toBeFalse()->and($result['message'])->toContain('not configured');
});
