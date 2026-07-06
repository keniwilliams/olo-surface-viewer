<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class OloPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('olo')
            ->path('olo')
            ->login()
            ->viteTheme('resources/css/app.css')
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => app(Vite::class)(['resources/js/app.js'])->toHtml(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => request()->routeIs('filament.olo.resources.database-connections.databases.surface-viewer')
                    ? view('filament.sidebar.surface-tree')->render()
                    : '',
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('Observation Cockpit')
                    ->icon(Heroicon::OutlinedPresentationChartLine)
                    ->url(fn (): string => route('filament.olo.resources.database-connections.databases.cockpit'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.olo.resources.database-connections.databases.cockpit'))
                    ->sort(1),

                NavigationItem::make('Surface Tree')
                    ->icon(Heroicon::OutlinedShare)
                    ->url(fn (): string => route('filament.olo.resources.database-connections.databases.surface-viewer'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.olo.resources.database-connections.databases.surface-viewer'))
                    ->sort(2),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
