<?php

namespace Tests\Feature\Bloodstream;

use LaravelNats\Publisher\Contracts\NatsPublisherContract;
use Mockery;
use Tests\TestCase;

class SetBloodstreamObserverMemoryWriteTest extends TestCase
{
    public function test_control_subject_defaults_to_the_memory_write_set_contract_subject(): void
    {
        $this->assertSame(
            'olo.bloodstream.observer.memory.write.set.v1',
            config('bloodstream.observer.control_subject'),
        );
    }

    public function test_disable_publishes_a_memory_write_false_control_message(): void
    {
        $publisher = Mockery::mock(NatsPublisherContract::class);
        $this->app->instance(NatsPublisherContract::class, $publisher);

        $publisher
            ->shouldReceive('publish')
            ->once()
            ->withArgs(function (string $subject, array $payload, array $headers, ?string $connection): bool {
                return $subject === 'olo.bloodstream.observer.memory.write.set.v1'
                    && $payload['memory_write_enabled'] === false
                    && $payload['requested_by'] === 'olo-surface-viewer'
                    && $payload['reason'] === 'pause writes'
                    && $connection === null;
            })
            ->andReturnNull();

        $this->artisan('olo:bloodstream-observer:memory-write disable --reason="pause writes"')
            ->assertSuccessful();
    }

    public function test_enable_publishes_a_memory_write_true_control_message(): void
    {
        $publisher = Mockery::mock(NatsPublisherContract::class);
        $this->app->instance(NatsPublisherContract::class, $publisher);

        $publisher
            ->shouldReceive('publish')
            ->once()
            ->withArgs(fn (string $subject, array $payload, array $headers, ?string $connection): bool => $subject === 'olo.bloodstream.observer.memory.write.set.v1'
                && $payload['memory_write_enabled'] === true)
            ->andReturnNull();

        $this->artisan('olo:bloodstream-observer:memory-write enable')
            ->assertSuccessful();
    }

    public function test_uses_configured_subject_and_connection(): void
    {
        config([
            'bloodstream.observer.control_subject' => 'custom.observer.memory.write.set',
            'bloodstream.observer.nats_connection' => 'observer',
        ]);

        $publisher = Mockery::mock(NatsPublisherContract::class);
        $this->app->instance(NatsPublisherContract::class, $publisher);

        $publisher
            ->shouldReceive('publish')
            ->once()
            ->withArgs(fn (string $subject, array $payload, array $headers, ?string $connection): bool => $subject === 'custom.observer.memory.write.set'
                && $connection === 'observer')
            ->andReturnNull();

        $this->artisan('olo:bloodstream-observer:memory-write disable')
            ->assertSuccessful();
    }

    public function test_invalid_state_fails_without_publishing(): void
    {
        $publisher = Mockery::mock(NatsPublisherContract::class);
        $this->app->instance(NatsPublisherContract::class, $publisher);

        $publisher->shouldNotReceive('publish');

        $this->artisan('olo:bloodstream-observer:memory-write sideways')
            ->assertFailed();
    }
}
