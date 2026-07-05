<?php

/** @noinspection PhpClassCanBeReadonlyInspection */

namespace App\Support\Bloodstream;

use App\Events\Bloodstream\BloodstreamObserverChanged;
use App\Services\Bloodstream\BloodstreamObserverPanelState;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use LaravelNats\Subscriber\InboundMessage;
use Throwable;

class BloodstreamObserverChangedPingHandler
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly BloodstreamObserverPanelState $panelState,
    ) {}

    /**
     * Dispatch the Laravel refresh signal for one observed change.
     *
     * The NATS body is parsed only for the small owner/event/publisher/
     * published_at/emitted_at ping contract, carried onto the event for
     * logging/diagnostics. It is never treated as display data and never
     * stored; missing or malformed metadata never blocks the refresh signal.
     */
    public function handle(InboundMessage $message): void
    {
        $ping = $message->decodedJson();

        $event = new BloodstreamObserverChanged(
            subject: $message->subject,
            receivedAt: CarbonImmutable::now(),
            owner: $this->stringOrNull($ping, 'owner'),
            event: $this->stringOrNull($ping, 'event'),
            publisher: $this->stringOrNull($ping, 'publisher'),
            publishedAt: $this->timestampOrNull($ping, 'published_at'),
            emittedAt: $this->timestampOrNull($ping, 'emitted_at'),
        );

        $this->events->dispatch($event);
        $this->panelState->recordChangedPing($event);
    }

    /**
     * @param  array<string, mixed>|null  $ping
     */
    private function stringOrNull(?array $ping, string $key): ?string
    {
        $value = $ping[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>|null  $ping
     */
    private function timestampOrNull(?array $ping, string $key): ?CarbonImmutable
    {
        $value = $this->stringOrNull($ping, $key);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
