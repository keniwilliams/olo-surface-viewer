<x-filament-panels::page>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div
        id="olo-observation-cockpit"
        data-organs-url="{{ url('/api/organs/state') }}"
        data-activity-url="{{ url('/api/activity/recent') }}"
    ></div>
</x-filament-panels::page>
