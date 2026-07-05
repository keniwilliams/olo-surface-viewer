<x-filament-panels::page>
    @php
        $status = $observer['status'] ?? 'unknown';
        $statusColor = match ($status) {
            'fresh' => 'success',
            'dirty' => 'warning',
            'stale' => 'warning',
            'error' => 'danger',
            'disabled' => 'gray',
            default => 'gray',
        };
        $ping = $observer['latest_ping'] ?? null;
        $metadata = $ping['metadata'] ?? [];
        $summary = $observer['summary'] ?? null;
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            Bloodstream Observer
        </x-slot>

        <x-slot name="description">
            Read-only Bloodstream memory state.
        </x-slot>

        <x-slot name="headerEnd">
            <x-filament::badge :color="$statusColor">
                {{ $status }}
            </x-filament::badge>
        </x-slot>

        <table>
            <tbody>
                <tr>
                    <th align="left" width="190">Latest changed ping</th>
                    <td><code>{{ $ping['received_at'] ?? 'none' }}</code></td>
                </tr>
                <tr>
                    <th align="left">Last refresh attempt</th>
                    <td><code>{{ $observer['last_refresh_attempt_at'] ?? 'none' }}</code></td>
                </tr>
                <tr>
                    <th align="left">Last successful read</th>
                    <td><code>{{ $observer['last_successful_read_at'] ?? 'none' }}</code></td>
                </tr>
                <tr>
                    <th align="left">Dirty</th>
                    <td><code>{{ ($observer['is_dirty'] ?? false) ? 'yes' : 'no' }}</code></td>
                </tr>
            </tbody>
        </table>

        @if ($observer['error'] ?? null)
            <div>
                <h3>Refresh state</h3>
                <code>{{ $observer['error'] }}</code>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Ping Diagnostics
        </x-slot>

        <table>
            <tbody>
                <tr>
                    <th align="left" width="190">Subject</th>
                    <td><code>{{ $ping['subject'] ?? 'none' }}</code></td>
                </tr>
                <tr>
                    <th align="left">Owner</th>
                    <td><code>{{ $metadata['owner'] ?? 'unknown' }}</code></td>
                </tr>
                <tr>
                    <th align="left">Event</th>
                    <td><code>{{ $metadata['event'] ?? 'unknown' }}</code></td>
                </tr>
                <tr>
                    <th align="left">Publisher</th>
                    <td><code>{{ $metadata['publisher'] ?? 'unknown' }}</code></td>
                </tr>
                <tr>
                    <th align="left">Published at</th>
                    <td><code>{{ $metadata['published_at'] ?? 'unknown' }}</code></td>
                </tr>
                <tr>
                    <th align="left">Emitted at</th>
                    <td><code>{{ $metadata['emitted_at'] ?? 'unknown' }}</code></td>
                </tr>
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Observer Memory
        </x-slot>

        @if ($summary)
            <table>
                <tbody>
                    <tr>
                        <th align="left" width="190">Contracts</th>
                        <td><code>{{ $summary['contracts_total'] }}</code></td>
                    </tr>
                    <tr>
                        <th align="left">Subjects</th>
                        <td><code>{{ $summary['subjects_total'] }}</code></td>
                    </tr>
                    <tr>
                        <th align="left">Contract statuses</th>
                        <td><code>{{ json_encode($summary['contracts_by_status']) }}</code></td>
                    </tr>
                    <tr>
                        <th align="left">Subject statuses</th>
                        <td><code>{{ json_encode($summary['subjects_by_status']) }}</code></td>
                    </tr>
                </tbody>
            </table>

            <div>
                <h3>Latest subject</h3>
                @if ($summary['latest_subject'])
                    <table>
                        <tbody>
                            <tr>
                                <th align="left" width="170">Subject</th>
                                <td><code>{{ $summary['latest_subject']['subject'] }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Organ</th>
                                <td><code>{{ $summary['latest_subject']['organ'] ?? 'unknown' }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Role</th>
                                <td><code>{{ $summary['latest_subject']['role'] ?? 'unknown' }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Status</th>
                                <td><code>{{ $summary['latest_subject']['status'] ?? 'unknown' }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Seen count</th>
                                <td><code>{{ $summary['latest_subject']['seen_count'] }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Last seen</th>
                                <td><code>{{ $summary['latest_subject']['last_seen_at'] ?? 'unknown' }}</code></td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <code>No subject memory rows found.</code>
                @endif
            </div>

            <div>
                <h3>Latest contract</h3>
                @if ($summary['latest_contract'])
                    <table>
                        <tbody>
                            <tr>
                                <th align="left" width="170">Contract</th>
                                <td><code>{{ $summary['latest_contract']['contract_key'] }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Organ</th>
                                <td><code>{{ $summary['latest_contract']['organ'] ?? 'unknown' }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Role</th>
                                <td><code>{{ $summary['latest_contract']['role'] ?? 'unknown' }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Status</th>
                                <td><code>{{ $summary['latest_contract']['status'] ?? 'unknown' }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Version</th>
                                <td><code>{{ $summary['latest_contract']['version'] ?? 'unknown' }}</code></td>
                            </tr>
                            <tr>
                                <th align="left">Updated</th>
                                <td><code>{{ $summary['latest_contract']['updated_at'] ?? 'unknown' }}</code></td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <code>No contract memory rows found.</code>
                @endif
            </div>
        @else
            <code>No Bloodstream observer memory has been read yet.</code>
        @endif
    </x-filament::section>
</x-filament-panels::page>
