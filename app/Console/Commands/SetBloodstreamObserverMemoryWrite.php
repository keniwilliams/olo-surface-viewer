<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;

/**
 * Drive the Bloodstream Observer memory-write valve from Surface Viewer.
 *
 * Publishes a single Observer-owned control message asking the Observer to
 * pause or resume writing newly discovered visibility memory. This is a valve
 * request, not a command surface: it targets the Observer's own control
 * subject, never a Bloodstream subject, and carries only the
 * `memory_write_enabled` intent plus audit metadata.
 */
class SetBloodstreamObserverMemoryWrite extends Command
{
    protected $signature = 'olo:bloodstream-observer:memory-write
                            {state : enable or disable}
                            {--reason= : Optional human reason recorded on the request}
                            {--connection= : Optional nats_basis connection name}';

    protected $description = 'Ask the Bloodstream Observer to enable or disable memory writes (Observer-owned valve).';

    public function handle(NatsPublisherContract $publisher): int
    {
        $enabled = $this->desiredState();

        if ($enabled === null) {
            $this->error('State must be "enable" or "disable".');

            return self::INVALID;
        }

        $subject = $this->controlSubject();

        if ($subject === '') {
            $this->error('Bloodstream Observer control subject is not configured.');

            return self::FAILURE;
        }

        $publisher->publish(
            $subject,
            [
                'memory_write_enabled' => $enabled,
                'requested_by' => $this->requestedBy(),
                'reason' => $this->reason($enabled),
            ],
            [],
            $this->connectionName(),
        );

        $this->info(sprintf(
            'Requested Observer memory writes %s on [%s].',
            $enabled ? 'ENABLED' : 'DISABLED',
            $subject,
        ));

        return self::SUCCESS;
    }

    private function desiredState(): ?bool
    {
        return match (strtolower(trim((string) $this->argument('state')))) {
            'enable' => true,
            'disable' => false,
            default => null,
        };
    }

    private function controlSubject(): string
    {
        $subject = config('bloodstream.observer.control_subject', 'olo.bloodstream.observer.memory.write.set.v1');

        return is_string($subject) ? trim($subject) : '';
    }

    private function requestedBy(): string
    {
        $requestedBy = config('bloodstream.observer.control_requested_by', 'olo-surface-viewer');

        return is_string($requestedBy) && trim($requestedBy) !== '' ? trim($requestedBy) : 'olo-surface-viewer';
    }

    private function reason(bool $enabled): string
    {
        $option = $this->option('reason');

        if (is_string($option) && trim($option) !== '') {
            return trim($option);
        }

        return $enabled
            ? 'resume newly discovered memory writes'
            : 'pause newly discovered memory writes';
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
}
