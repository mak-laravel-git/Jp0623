<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TwilioSmsService
{
    public function __construct(private TwilioSettingsService $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->isReady();
    }

    /**
     * @return array{sid: string|null, status: string}
     *
     * @throws RuntimeException
     * @throws ConnectionException
     */
    public function send(string $toE164, string $body): array
    {
        $config = $this->settings->current();

        if (! $this->settings->isReady($config)) {
            throw new RuntimeException('Twilio SMS is not configured.');
        }

        $sid = (string) $config['sid'];
        $token = (string) $config['token'];
        $sandboxPrefix = trim((string) ($config['sandbox_message_prefix'] ?? ''));

        if ($sandboxPrefix !== '' && ! str_starts_with($body, $sandboxPrefix)) {
            $body = trim($sandboxPrefix.' '.$body);
        }

        $payload = [
            'To' => $toE164,
            'Body' => $body,
        ];

        if (filled($config['messaging_service_sid'] ?? null)) {
            $payload['MessagingServiceSid'] = (string) $config['messaging_service_sid'];
        } else {
            $payload['From'] = (string) $config['from'];
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(2, 500, fn (\Throwable $exception): bool => $exception instanceof ConnectionException)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload);

        if ($response->failed()) {
            Log::warning('Twilio SMS send failed.', [
                'to' => $toE164,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            $response->throw();
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return [
            'sid' => isset($json['sid']) ? (string) $json['sid'] : null,
            'status' => isset($json['status']) ? (string) $json['status'] : 'queued',
        ];
    }
}
