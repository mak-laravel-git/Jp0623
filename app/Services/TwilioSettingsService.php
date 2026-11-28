<?php

namespace App\Services;

use App\Models\TwilioSetting;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Twilio settings edited by the admin. They are stored in the database (the auth token encrypted) and take priority
 * over `config('services.twilio')`; any field left empty keeps the `.env` value.
 *
 * The database is read on every call instead of once at boot, so a long-running queue worker uses new settings
 * without being restarted.
 */
class TwilioSettingsService
{
    /**
     * Database column => `services.twilio` config key.
     */
    private const CONFIG_KEYS = [
        'enabled' => 'enabled',
        'sid' => 'sid',
        'auth_token' => 'token',
        'from_number' => 'from',
        'messaging_service_sid' => 'messaging_service_sid',
        'lead_radius_miles' => 'lead_radius_miles',
    ];

    private const TEXT_FIELDS = ['sid', 'from_number', 'messaging_service_sid', 'lead_radius_miles'];

    /**
     * The Twilio config in effect right now.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $config = $this->envConfig();
        $row = $this->row();

        if ($row === null) {
            return $config;
        }

        foreach (self::CONFIG_KEYS as $column => $key) {
            if ($column === 'auth_token') {
                try {
                    $value = $row->auth_token;
                } catch (DecryptException) {
                    // Saved with another APP_KEY. Never pair the saved account with a token from somewhere else.
                    Log::warning('The saved Twilio auth token could not be decrypted.');
                    $config[$key] = null;

                    continue;
                }
            } else {
                $value = $row->{$column};
            }

            if (filled($value)) {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    /**
     * Whether SMS can be sent with the given config: switched on, with credentials and a sender.
     *
     * @param  array<string, mixed>|null  $config
     */
    public function isReady(?array $config = null): bool
    {
        $config ??= $this->current();

        return (bool) ($config['enabled'] ?? false)
            && filled($config['sid'] ?? null)
            && filled($config['token'] ?? null)
            && (filled($config['messaging_service_sid'] ?? null) || filled($config['from'] ?? null));
    }

    public function leadRadiusMiles(): float
    {
        return (float) ($this->current()['lead_radius_miles'] ?? 100);
    }

    /**
     * What the settings page shows. The auth token is never returned, only whether it is set and its last 4 characters.
     *
     * @return array{
     *     enabled: array{value: bool, source: string},
     *     fields: array<string, array{value: string|null, source: string}>,
     *     secrets: array<string, array{configured: bool, last4: string|null, source: string}>,
     *     updated_by: string|null,
     *     updated_at: string|null,
     *     is_configured: bool,
     * }
     */
    public function summary(): array
    {
        $row = $this->row()?->load('updater:id,name');
        $env = $this->envConfig();
        $config = $this->current();
        $fields = [];

        foreach (self::TEXT_FIELDS as $field) {
            $key = self::CONFIG_KEYS[$field];
            $value = filled($config[$key] ?? null) ? (string) $config[$key] : null;

            $fields[$field] = [
                'value' => $value,
                'source' => filled($row?->{$field}) ? 'admin' : (filled($env[$key] ?? null) ? 'env' : 'missing'),
            ];
        }

        $token = filled($config['token'] ?? null) ? (string) $config['token'] : null;
        $tokenSavedHere = filled($row?->getRawOriginal('auth_token'));

        return [
            'enabled' => [
                'value' => (bool) ($config['enabled'] ?? false),
                'source' => $row?->enabled === null ? 'env' : 'admin',
            ],
            'fields' => $fields,
            'secrets' => [
                'auth_token' => [
                    'configured' => $token !== null,
                    'last4' => $token !== null ? substr($token, -4) : null,
                    'source' => $tokenSavedHere ? 'admin' : ($token !== null ? 'env' : 'missing'),
                ],
            ],
            'updated_by' => $row?->updater?->name,
            'updated_at' => $row?->updated_at?->toIso8601String(),
            'is_configured' => $this->isReady($config),
        ];
    }

    /**
     * Save the form. Empty plain fields go back to `.env`; an empty token keeps the saved one unless it is cleared.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, User $admin): TwilioSetting
    {
        $row = TwilioSetting::query()->first() ?? new TwilioSetting;

        $row->enabled = (bool) $data['enabled'];

        foreach (self::TEXT_FIELDS as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $row->{$field} = $value === '' ? null : $value;
        }

        $token = trim((string) ($data['auth_token'] ?? ''));

        if (! empty($data['clear_auth_token'])) {
            $row->auth_token = null;
        } elseif ($token !== '') {
            $row->auth_token = $token;
        }

        $row->updated_by = $admin->id;
        $row->save();

        return $row;
    }

    /**
     * Read the account (and Messaging Service) from Twilio to prove the settings work. Sends no SMS.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $config = $this->current();
        $sid = (string) ($config['sid'] ?? '');
        $token = (string) ($config['token'] ?? '');

        if ($sid === '' || $token === '') {
            return ['ok' => false, 'message' => 'Twilio is not configured yet. Save the Account SID and auth token first.'];
        }

        try {
            $account = $this->get($sid, $token, "https://api.twilio.com/2010-04-01/Accounts/{$sid}.json");

            if ($account->failed()) {
                return ['ok' => false, 'message' => 'Twilio rejected the credentials: '.($account->json('message') ?? 'HTTP '.$account->status()).'.'];
            }

            $message = 'Connected to Twilio account "'.($account->json('friendly_name') ?? $sid).'"';
            $messagingServiceSid = (string) ($config['messaging_service_sid'] ?? '');

            if ($messagingServiceSid !== '') {
                $service = $this->get($sid, $token, 'https://messaging.twilio.com/v1/Services/'.urlencode($messagingServiceSid));

                if ($service->failed()) {
                    return ['ok' => false, 'message' => 'The credentials work, but the Messaging Service was not found in this account.'];
                }

                $message .= ' and Messaging Service "'.($service->json('friendly_name') ?? $messagingServiceSid).'"';
            }
        } catch (ConnectionException) {
            return ['ok' => false, 'message' => 'Could not reach Twilio. Try again in a moment.'];
        }

        if (! $this->isReady($config)) {
            $message .= ', but SMS sending is still off. Switch it on and set a Messaging Service SID or sender number';
        }

        return ['ok' => true, 'message' => $message.'.'];
    }

    private function get(string $sid, string $token, string $url): Response
    {
        return Http::withBasicAuth($sid, $token)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->get($url);
    }

    /**
     * The saved row, or null when nothing is saved or the table cannot be read yet (before `migrate`).
     */
    private function row(): ?TwilioSetting
    {
        try {
            return TwilioSetting::query()->first();
        } catch (Throwable $exception) {
            Log::warning('Twilio settings could not be read.', ['error' => $exception::class]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function envConfig(): array
    {
        return (array) config('services.twilio', []);
    }
}
