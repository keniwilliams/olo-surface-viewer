<?php

namespace App\Services\SurfaceTree;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read path for Camera Lens runtime telemetry (OOC-2 / OCL-19). Unlike the
 * other surface tree feeds this is not a database read: telemetry only
 * exists as log lines in Loki, fed by olo-nats-tap subscribing to
 * olo.camera_lens.runtime.event. Queried over Loki's HTTP query_range API,
 * not Eloquent.
 */
class CameraLensTelemetryFeed
{
    private const QUERY = '{container="/olo-nats-tap"} | json | payload_organism="camera_lens"';

    private const LOOKBACK_HOURS = 24;

    private const LIMIT = 200;

    /**
     * @return list<array<string, mixed>>
     */
    public function latestEvents(): array
    {
        try {
            $response = Http::baseUrl(config('services.loki.base_url'))
                ->timeout(5)
                ->get('/loki/api/v1/query_range', [
                    'query' => self::QUERY,
                    'start' => $this->nanoseconds(now()->subHours(self::LOOKBACK_HOURS)->timestamp),
                    'end' => $this->nanoseconds(now()->timestamp),
                    'limit' => self::LIMIT,
                    'direction' => 'backward',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $events = [];

            foreach ($response->json('data.result') ?? [] as $stream) {
                foreach ($stream['values'] ?? [] as $value) {
                    $event = $this->decodeLine($value[0] ?? null, $value[1] ?? null);

                    if ($event !== null) {
                        $events[] = $event;
                    }
                }
            }

            usort($events, static fn (array $a, array $b): int => $b['timestamp_ns'] <=> $a['timestamp_ns']);

            return array_slice($events, 0, self::LIMIT);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeLine(?string $timestampNs, ?string $line): ?array
    {
        if ($timestampNs === null || $line === null) {
            return null;
        }

        $decoded = json_decode($line, true);

        if (! is_array($decoded)) {
            return null;
        }

        $payload = $decoded['payload'] ?? [];

        if (! is_array($payload)) {
            $payload = [];
        }

        $event = $payload;
        $event['timestamp_ns'] = $timestampNs;
        $event['subject'] = $decoded['subject'] ?? null;
        $event['correlation_id'] = $payload['correlation_id'] ?? $decoded['correlation_id'] ?? null;

        return $event;
    }

    private function nanoseconds(int $unixSeconds): string
    {
        return (string) ($unixSeconds * 1_000_000_000);
    }
}
