<?php

namespace App\Console\Commands;

use App\Support\Bloodstream\BloodstreamObserverChangedPingHandler;
use Illuminate\Console\Command;
use LaravelNats\Subscriber\Contracts\NatsSubscriberContract;
use LaravelNats\Subscriber\InboundMessage;

class ListenForBloodstreamObserverChanged extends Command
{
    protected $signature = 'olo:bloodstream-observer:listen
                            {--connection= : Optional nats_basis connection name}
                            {--timeout= : Blocking timeout, in seconds, for each NATS process loop}
                            {--once : Process one loop then stop}';

    protected $description = 'Consume Bloodstream Observer changed pings and signal Laravel refresh hooks.';

    private bool $shouldQuit = false;

    public function handle(
        NatsSubscriberContract $subscriber,
        BloodstreamObserverChangedPingHandler $handler,
    ): int {
        $subject = $this->changedSubject();

        if ($subject === '') {
            $this->error('Bloodstream Observer changed subject is not configured.');

            return self::FAILURE;
        }

        $connection = $this->connectionName();
        $timeout = $this->listenTimeout();
        $once = (bool) $this->option('once');

        $this->registerSignalHandlers();

        $this->info("Listening for Bloodstream Observer changed pings on [{$subject}].");

        $subscriptionId = $subscriber->subscribe(
            subject: $subject,
            handler: fn (InboundMessage $message): null => $this->handlePing($handler, $message),
            queueGroup: null,
            connection: $connection,
        );

        try {
            do {
                $subscriber->process($connection, $timeout);
            } while (! $once && ! $this->shouldQuit);
        } finally {
            $subscriber->unsubscribe($subscriptionId);
        }

        return self::SUCCESS;
    }

    private function handlePing(BloodstreamObserverChangedPingHandler $handler, InboundMessage $message): null
    {
        $handler->handle($message);
        $this->line('Bloodstream Observer changed ping received; refresh hooks signalled.');

        return null;
    }

    private function changedSubject(): string
    {
        $subject = config('bloodstream.observer.changed_subject', 'olo.bloodstream.observer.changed.v1');

        return is_string($subject) ? trim($subject) : '';
    }

    private function connectionName(): ?string
    {
        $option = $this->option('connection');

        if (is_string($option) && trim($option) !== '') {
            return trim($option);
        }

        $configured = config('bloodstream.observer.nats_connection');

        return is_string($configured) && trim($configured) !== '' ? trim($configured) : null;
    }

    private function listenTimeout(): float
    {
        $option = $this->option('timeout');
        $configured = $option !== null && $option !== ''
            ? $option
            : config('bloodstream.observer.listen_timeout', 1.0);

        if (! is_numeric($configured)) {
            return 1.0;
        }

        return max((float) $configured, 0.0);
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_async_signals') || ! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, function (): void {
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGTERM, function (): void {
            $this->shouldQuit = true;
        });
    }
}
