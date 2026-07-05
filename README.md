# OLO Surface Viewer

![CI](https://github.com/keniwilliams/olo-surface-viewer/actions/workflows/ci.yml/badge.svg)
![PHPStan](https://img.shields.io/badge/PHPStan-Larastan%20level%205-blueviolet)
![Laravel](https://img.shields.io/badge/Laravel-13.x-red)

OLO Surface Viewer is a Laravel + Filament application for inspecting database visibility, schema snapshots, and observer-facing runtime metadata across a distributed local system.

It solves a practical engineering problem: instead of checking several databases, command outputs, logs, and service-specific tools separately, Surface Viewer gives one read-only place to inspect what the system can currently see.

This repository is a portfolio-facing slice of a larger OLO system. The focus is not a finished product UI; it is the engineering boundary around safe observation: explicit database registration, schema capture, local snapshot storage, read-only access, and a foundation for rendering domain-specific surfaces.

## Current Purpose

Surface Viewer currently provides:

- read-only registration of known observed database connections
- syncing configured database connections into a local registry
- schema metadata capture from enabled observed databases
- local storage of schema snapshots inside the Surface Viewer database
- Filament pages for database-specific visibility
- a safe foundation for richer schema viewing

It is not intended to mutate external system databases or act as a production multi-tenant database discovery service.

## Architecture Position

Surface Viewer is an observation surface.

It does not own organ data.

It does not mutate organ databases.

It stores local metadata about what it observes.

```
configured observed database connections
    ?
olo:database-connections:sync
    ?
database_connections
    ?
olo:database-schemas:pull
    ?
database_schema_snapshots
database_table_schemas
    ?
future schema viewer UI
```

## Observed Databases

The known observed database connection keys are:

- surface_viewer
- bloodstream
- subconscious
- impressions
- sidecar

These are configured through Laravel database connection config and environment variables.

Connection values are intentionally not discovered dynamically from the Surface Viewer database. The static config approach keeps the tool explicit, cacheable, low-overhead, and suitable for local/internal use.

If Surface Viewer later becomes a production or multi-tenant service, dynamic discovery may be reconsidered.

## Important Boundary

Surface Viewer may inspect configured databases, but organ databases remain external systems.

Surface Viewer should not write to:

- Bloodstream
- Subconscious / Dreamstate
- Impressions
- Sidecar

The only database Surface Viewer owns is its own application database.

## Local Setup

Install dependencies:

```powershell
composer install
npm install
```

Copy the environment file:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Configure the main Surface Viewer database using the normal Laravel database variables:

```env
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

Optional observed database variables may be supplied for the organ databases:

```env
BLOODSTREAM_DB_HOST=
BLOODSTREAM_DB_PORT=
BLOODSTREAM_DB_DATABASE=
BLOODSTREAM_DB_USERNAME=
BLOODSTREAM_DB_PASSWORD=

SUBCONSCIOUS_DB_HOST=
SUBCONSCIOUS_DB_PORT=
SUBCONSCIOUS_DB_DATABASE=
SUBCONSCIOUS_DB_USERNAME=
SUBCONSCIOUS_DB_PASSWORD=

IMPRESSIONS_DB_HOST=
IMPRESSIONS_DB_PORT=
IMPRESSIONS_DB_DATABASE=
IMPRESSIONS_DB_USERNAME=
IMPRESSIONS_DB_PASSWORD=

SIDECAR_DB_HOST=
SIDECAR_DB_PORT=
SIDECAR_DB_DATABASE=
SIDECAR_DB_USERNAME=
SIDECAR_DB_PASSWORD=
```

Do not commit local topology values or credentials.

## Migrate

```powershell
php artisan migrate
```

Create a Filament user if needed:

```powershell
php artisan make:filament-user
```

Run the local server:

```powershell
php artisan serve --host=127.0.0.1 --port=8100
```

Open:

```
http://127.0.0.1:8100/olo
```

## Core Commands

Sync configured observed databases into the local registry:

```powershell
php artisan olo:database-connections:sync
```

Pull schema metadata for all enabled observed databases:

```powershell
php artisan olo:database-schemas:pull
```

Pull schema metadata for one observed database:

```powershell
php artisan olo:database-schemas:pull surface_viewer
```

Listen for Bloodstream Observer changed pings:

```powershell
php artisan olo:bloodstream-observer:listen
```

The listener subscribes to one NATS subject only:

```
olo.bloodstream.observer.changed.v1
```

The NATS message is a ping/dirty signal, not display data. Its body carries only a small metadata contract — `owner`, `event`, `publisher`, `published_at`, `emitted_at` — which the listener parses onto the dispatched `BloodstreamObserverChanged` event for logging/diagnostics only; it is never stored and never used as display data. Surface Viewer refresh hooks should read current observer memory from the Bloodstream database through the read-only Laravel models and JSON APIs.

## Registry Sync Behaviour

The registry sync command creates or updates rows in:

```
database_connections
```

A connection is marked enabled only when the required connection metadata is present:

- host
- port
- database
- username

Incomplete connections are synced as disabled rows.

Passwords are not stored in the registry.

## Schema Snapshot Storage

Schema metadata is stored locally in:

```
database_schema_snapshots
database_table_schemas
```

The snapshot tables use soft references rather than database-enforced foreign keys.

The Foreign_keys field in table schema metadata describes observed foreign keys from inspected databases. It does not create foreign key constraints inside Surface Viewer.

## Current Filament Pages

Database visibility pages live under the Database Connections resource.

Current page classes:

```
app/Filament/Resources/DatabaseConnections/Pages/Databases/Overview.php
app/Filament/Resources/DatabaseConnections/Pages/Databases/SurfaceViewer.php
app/Filament/Resources/DatabaseConnections/Pages/Databases/Bloodstream.php
app/Filament/Resources/DatabaseConnections/Pages/Databases/Subconscious.php
app/Filament/Resources/DatabaseConnections/Pages/Databases/Impressions.php
app/Filament/Resources/DatabaseConnections/Pages/Databases/Sidecar.php
```

Current route shape:

```
/olo/database-connections/databases/overview
/olo/database-connections/databases/surface-viewer
/olo/database-connections/databases/bloodstream
/olo/database-connections/databases/subconscious
/olo/database-connections/databases/impressions
/olo/database-connections/databases/sidecar
```

Schema rendering is intentionally not complete yet.

## Tests

Run all tests:

```powershell
php artisan test
```

Focused tests:

```powershell
php artisan test --filter=DatabaseConnectionModelTest
php artisan test --filter=DatabaseSchemaModelsTest
php artisan test --filter=ObservedDatabaseConnectionsTest
php artisan test --filter=SyncDatabaseConnectionsCommandTest
```

## Current Clean History

The intended clean main history is:

```
Initial OLO Surface Viewer scaffold
Add database visibility foundation
Add database schema snapshot pull foundation
Add observed database registry sync
```

Do not rewrite this history unless there is a real leak or explicit reason.

## Development Rules

- Keep Surface Viewer as an observer, not an organ owner.
- Do not store passwords in registry tables.
- Do not commit local topology values.
- Prefer explicit configured observed database connections over dynamic discovery.
- Keep PRs focused.
- Do not mix schema capture guts with schema rendering UI.
- Use Filament-native UI patterns when rendering is added.
- Ask before deciding the schema viewer interaction model.

## Next Likely Work

The next major feature should be a proper schema viewer.

Before implementing it, decide the interaction model:

- Filament table
- modal or slide-over detail
- Livewire explorer
- server-rendered detail navigation
- full schema browser

Do not assume AJAX or no-AJAX without a product decision.
