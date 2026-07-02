<?php

namespace Tests\Feature\Bloodstream;

use App\Events\Bloodstream\BloodstreamObserverChanged;
use App\Support\Bloodstream\BloodstreamObserverChangedPingHandler;
use Exception;
use Illuminate\Support\Facades\Event;
use JsonException;
use LaravelNats\Subscriber\Contracts\NatsSubscriberContract;
use LaravelNats\Subscriber\InboundMessage;
use Mockery;
use Tests\TestCase;

class BloodstreamObserverChangedPingTest extends TestCase
{
    public function test_observer_changed_subject_defaults_to_the_ping_contract_subject(): void
    {
        $this->assertSame(
            'olo.bloodstream.observer.changed.v1',
            config('bloodstream.observer.changed_subject'),
        );
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function test_ping_handler_parses_the_metadata_only_contract_shape(): void
    {
        Event::fake();

        $message = new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: json_encode([
                'owner' => 'olo-bloodstream-observer',
                'event' => 'changed',
                'publisher' => 'impressions',
                'published_at' => '2026-07-02T00:12:03.104112+00:00',
                'emitted_at' => '2026-07-02T00:12:03.201933+00:00',
            ], JSON_THROW_ON_ERROR),
            headers: [],
            replyTo: null,
        );

        app(BloodstreamObserverChangedPingHandler::class)->handle($message);

        Event::assertDispatched(
            BloodstreamObserverChanged::class,
            function (BloodstreamObserverChanged $event): bool {
                return $event->subject === 'olo.bloodstream.observer.changed.v1'
                    && $event->owner === 'olo-bloodstream-observer'
                    && $event->event === 'changed'
                    && $event->publisher === 'impressions'
                    && $event->publishedAt?->toIso8601String() === '2026-07-02T00:12:03+00:00'
                    && $event->emittedAt?->toIso8601String() === '2026-07-02T00:12:03+00:00';
            },
        );
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function test_ping_handler_never_carries_the_old_envelope_or_display_data_onto_the_event(): void
    {
        Event::fake();

        $message = new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: json_encode([
                'source' => 'olo-bloodstream-observer',
                'event' => 'changed',
                'payload' => ['must_not_be_used' => true],
                'payload_decode' => 'json',
                'observed_at' => '2026-07-02T00:12:03.104112+00:00',
                'subject' => 'olo.impressions.events.impression.created.v1',
            ], JSON_THROW_ON_ERROR),
            headers: [],
            replyTo: null,
        );

        app(BloodstreamObserverChangedPingHandler::class)->handle($message);

        Event::assertDispatched(
            BloodstreamObserverChanged::class,
            fn (BloodstreamObserverChanged $event): bool => $event->subject === 'olo.bloodstream.observer.changed.v1'
                && ! property_exists($event, 'body')
                && ! property_exists($event, 'payload')
                && ! property_exists($event, 'payload_decode')
                // no top-level "owner"/"publisher" in this body -> metadata stays null
                && $event->owner === null
                && $event->publisher === null,
        );
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function test_ping_handler_tolerates_missing_or_malformed_metadata_without_breaking_the_refresh_signal(): void
    {
        Event::fake();

        $message = new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: json_encode([
                'owner' => 'olo-bloodstream-observer',
                'event' => 'changed',
                'publisher' => null,
                'published_at' => 'not-a-timestamp',
            ], JSON_THROW_ON_ERROR),
            headers: [],
            replyTo: null,
        );

        app(BloodstreamObserverChangedPingHandler::class)->handle($message);

        Event::assertDispatched(
            BloodstreamObserverChanged::class,
            fn (BloodstreamObserverChanged $event): bool => $event->subject === 'olo.bloodstream.observer.changed.v1'
                && $event->publisher === null
                && $event->publishedAt === null
                && $event->emittedAt === null,
        );
    }

    /**
     * @throws Exception
     */
    public function test_ping_handler_tolerates_an_empty_body(): void
    {
        Event::fake();

        $message = new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: '',
            headers: [],
            replyTo: null,
        );

        app(BloodstreamObserverChangedPingHandler::class)->handle($message);

        Event::assertDispatched(
            BloodstreamObserverChanged::class,
            fn (BloodstreamObserverChanged $event): bool => $event->subject === 'olo.bloodstream.observer.changed.v1'
                && $event->owner === null
                && $event->event === null
                && $event->publisher === null
                && $event->publishedAt === null
                && $event->emittedAt === null,
        );
    }

    public function test_listener_subscribes_to_the_single_configured_subject_and_unsubscribes(): void
    {
        Event::fake();

        $subscriber = Mockery::mock(NatsSubscriberContract::class);
        $this->app->instance(NatsSubscriberContract::class, $subscriber);

        $subscriber
            ->shouldReceive('subscribe')
            ->once()
            ->withArgs(function (string $subject, callable $handler, ?string $queueGroup, ?string $connection): bool {
                $handler(new InboundMessage(
                    subject: $subject,
                    body: json_encode([
                        'owner' => 'olo-bloodstream-observer',
                        'event' => 'changed',
                        'publisher' => 'sidecar',
                        'published_at' => null,
                        'emitted_at' => '2026-07-02T00:12:03.201933+00:00',
                    ], JSON_THROW_ON_ERROR),
                    headers: [],
                    replyTo: null,
                ));

                return $subject === 'olo.bloodstream.observer.changed.v1'
                    && $queueGroup === null
                    && $connection === null;
            })
            ->andReturn('subscription-id');

        $subscriber
            ->shouldReceive('process')
            ->once()
            ->with(null, 1.0)
            ->andReturnNull();

        $subscriber
            ->shouldReceive('unsubscribe')
            ->once()
            ->with('subscription-id')
            ->andReturnNull();

        $this->artisan('olo:bloodstream-observer:listen --once')
            ->assertSuccessful();

        Event::assertDispatched(BloodstreamObserverChanged::class);
    }

    public function test_listener_uses_configured_subject_without_accepting_a_runtime_subject_argument(): void
    {
        config([
            'bloodstream.observer.changed_subject' => 'custom.observer.changed',
            'bloodstream.observer.nats_connection' => 'observer',
        ]);

        $subscriber = Mockery::mock(NatsSubscriberContract::class);
        $this->app->instance(NatsSubscriberContract::class, $subscriber);

        $subscriber
            ->shouldReceive('subscribe')
            ->once()
            ->withArgs(fn (string $subject, callable $handler, ?string $queueGroup, ?string $connection): bool => $subject === 'custom.observer.changed'
                && $queueGroup === null
                && $connection === 'observer')
            ->andReturn('subscription-id');

        $subscriber
            ->shouldReceive('process')
            ->once()
            ->with('observer', 1.0)
            ->andReturnNull();

        $subscriber
            ->shouldReceive('unsubscribe')
            ->once()
            ->with('subscription-id')
            ->andReturnNull();

        $this->artisan('olo:bloodstream-observer:listen --once')
            ->assertSuccessful();
    }
}
