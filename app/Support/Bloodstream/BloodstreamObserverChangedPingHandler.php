<?php

namespace App\Support\Bloodstream;

use App\Events\Bloodstream\BloodstreamObserverChanged;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use LaravelNats\Subscriber\InboundMessage;

class BloodstreamObserverChangedPingHandler
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function handle(InboundMessage $message): void
    {
        $this->events->dispatch(new BloodstreamObserverChanged(
            subject: $message->subject,
            receivedAt: CarbonImmutable::now(),
        ));
    }
}
