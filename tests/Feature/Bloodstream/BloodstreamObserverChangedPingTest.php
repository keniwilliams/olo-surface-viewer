<?php

namespace Tests\Feature\Bloodstream;

use App\Events\Bloodstream\BloodstreamObserverChanged;
use App\Support\Bloodstream\BloodstreamObserverChangedPingHandler;
use Illuminate\Support\Facades\Event;
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

    public function test_ping_handler_ignores_body_and_dispatches_refresh_signal_only(): void
    {
        Event::fake();

        $message = new InboundMessage(
            subject: 'olo.bloodstream.observer.changed.v1',
            body: json_encode([
                'source' => 'olo-bloodstream-observer',
                'event' => 'changed',
                'payload' => ['must_not_be_used' => true],
            ], JSON_THROW_ON_ERROR),
            headers: [],
            replyTo: null,
        );

        app(BloodstreamObserverChangedPingHandler::class)->handle($message);

        Event::assertDispatched(
            BloodstreamObserverChanged::class,
            fn (BloodstreamObserverChanged $event): bool => $event->subject === 'olo.bloodstream.observer.changed.v1'
                && ! property_exists($event, 'body')
                && ! property_exists($event, 'payload'),
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
                    body: '{"payload":{"old":"feed-envelope"}}',
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
