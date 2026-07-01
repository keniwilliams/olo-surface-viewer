<x-filament-panels::page>
    @foreach ($databases as $database)
        <x-filament::section>
            <x-slot name="heading">
                {{ $database['label'] }}
            </x-slot>

            <x-slot name="description">
                {{ $database['connection'] }}
            </x-slot>

            <x-slot name="headerEnd">
                <x-filament::badge
                    :color="$database['status'] === 'online' ? 'success' : 'danger'"
                >
                    {{ $database['status'] }}
                </x-filament::badge>
            </x-slot>

            <table>
                <tbody>
                    <tr>
                        <th align="left" width="120">Host</th>
                        <td><code>{{ $database['host'] }}</code></td>
                    </tr>
                    <tr>
                        <th align="left">Port</th>
                        <td><code>{{ $database['port'] }}</code></td>
                    </tr>
                    <tr>
                        <th align="left">Database</th>
                        <td><code>{{ $database['database'] }}</code></td>
                    </tr>
                    <tr>
                        <th align="left">User</th>
                        <td><code>{{ $database['username'] }}</code></td>
                    </tr>
                    <tr>
                        <th align="left">Tables</th>
                        <td><code>{{ $database['table_count'] ?? 'n/a' }}</code></td>
                    </tr>
                </tbody>
            </table>

            @if ($database['error'])
                <x-filament::section compact>
                    <x-slot name="heading">
                        Error
                    </x-slot>

                    <code>{{ $database['error'] }}</code>
                </x-filament::section>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
