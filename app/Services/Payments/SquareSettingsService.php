<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\SquareNotConfiguredException;
use App\Exceptions\Payments\SquarePaymentException;
use App\Models\SquareSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Square credentials edited by the admin. They are stored in the database (the secrets encrypted) and laid over
 * `config('services.square')` when the app boots; any field left empty keeps the `.env` value.
 */
class SquareSettingsService
{
    public const ENVIRONMENTS = ['sandbox', 'production'];

    private const FIELDS = ['environment', 'application_id', 'location_id', 'webhook_url', 'access_token', 'webhook_signature_key'];

    private const SECRETS = ['access_token', 'webhook_signature_key'];

    /**
     * The `.env` values, captured before the admin values are laid over them.
     *
     * @var array<string, mixed>|null
     */
    private ?array $envValues = null;

    public function __construct(private SquareClient $client) {}

    /**
     * Lay the admin values over the config. Safe to call before the table exists (e.g. during `migrate`).
     */
    public function apply(): void
    {
        $this->envValues ??= $this->currentConfig();

        try {
            $row = SquareSetting::query()->first();

            if ($row === null) {
                return;
            }

            foreach (self::FIELDS as $field) {
                $value = $row->{$field};

                if (filled($value)) {
                    config(['services.square.'.$field => $value]);
                }
            }

            if (filled($row->environment)) {
                $production = $row->environment === 'production';

                config([
                    'services.square.base_url' => $production ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com',
                    'services.square.sdk_url' => $production ? 'https://web.squarecdn.com/v1/square.js' : 'https://sandbox.web.squarecdn.com/v1/square.js',
                ]);
            }
        } catch (Throwable $exception) {
            // A missing table or an unreadable (re-keyed) secret must never take the whole site down.
            Log::warning('Square settings could not be applied.', ['error' => $exception::class]);
        }
    }

    /**
     * What the settings page shows. Secrets are never returned, only whether they are set and their last 4 characters.
     *
     * @return array{
     *     fields: array<string, array{value: string|null, source: string}>,
     *     secrets: array<string, array{configured: bool, last4: string|null, source: string}>,
     *     default_webhook_url: string,
     *     updated_by: string|null,
     *     updated_at: string|null,
     *     is_configured: bool,
     * }
     */
    public function summary(): array
    {
        $row = SquareSetting::query()->with('updater:id,name')->first();
        $env = $this->envValues ?? $this->currentConfig();
        $fields = [];
        $secrets = [];

        foreach (self::FIELDS as $field) {
            $own = $row?->{$field};
            $source = filled($own) ? 'admin' : (filled($env[$field] ?? null) ? 'env' : 'missing');
            $effective = filled($own) ? (string) $own : (filled($env[$field] ?? null) ? (string) $env[$field] : null);

            if (in_array($field, self::SECRETS, true)) {
                $secrets[$field] = [
                    'configured' => $effective !== null,
                    'last4' => $effective !== null ? substr($effective, -4) : null,
                    'source' => $source,
                ];

                continue;
            }

            $fields[$field] = ['value' => $effective, 'source' => $source];
        }

        return [
            'fields' => $fields,
            'secrets' => $secrets,
            'default_webhook_url' => url('/webhooks/square'),
            'updated_by' => $row?->updater?->name,
            'updated_at' => $row?->updated_at?->toIso8601String(),
            'is_configured' => $this->client->isConfigured(),
        ];
    }

    /**
     * Save the form. Empty plain fields go back to `.env`; an empty secret keeps the saved one unless it is cleared.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, User $admin): SquareSetting
    {
        $row = SquareSetting::query()->first() ?? new SquareSetting;

        foreach (['environment', 'application_id', 'location_id', 'webhook_url'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $row->{$field} = $value === '' ? null : $value;
        }

        foreach (self::SECRETS as $secret) {
            $value = trim((string) ($data[$secret] ?? ''));

            if (! empty($data['clear_'.$secret])) {
                $row->{$secret} = null;
            } elseif ($value !== '') {
                $row->{$secret} = $value;
            }
        }

        $row->updated_by = $admin->id;
        $row->save();

        return $row;
    }

    /**
     * Ask Square for the configured location to prove the token, application and location work together.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $location = $this->client->retrieveLocation();
        } catch (SquareNotConfiguredException) {
            return ['ok' => false, 'message' => 'Square is not configured yet. Save the application id, access token and location id first.'];
        } catch (SquarePaymentException $exception) {
            return ['ok' => false, 'message' => $exception->connectionFailed
                ? 'Could not reach Square. Try again in a moment.'
                : 'Square rejected the credentials: '.($exception->detail ?? $exception->errorCode ?? 'unknown error').'.'];
        }

        $name = (string) ($location['name'] ?? $location['id'] ?? 'your location');
        $status = strtolower((string) ($location['status'] ?? 'active'));

        return ['ok' => true, 'message' => "Connected to Square location \"{$name}\" ({$status})."];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentConfig(): array
    {
        return (array) config('services.square', []);
    }
}
