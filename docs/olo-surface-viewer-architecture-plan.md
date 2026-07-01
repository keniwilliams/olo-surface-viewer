# OLO Surface Viewer Architecture Plan

**Status:** Planning
**Repo:** `D:\OLO-Software\olo-surface-viewer`
**Application:** OLO Surface Viewer
**Stack:** Laravel, Filament shell, Vue page bodies, JSON APIs

---

## 1. Purpose

OLO Surface Viewer is not just a database schema browser.

It is the inspection and interaction surface for the OLO organism.

The application should allow the user to inspect structure, inspect raw data, run meaningful organism queries, and later work through purpose-built layouts such as markdown editing, email inspection, filter management, and Bloodstream visibility.

The current architecture direction is deliberately Vue-first inside a Filament-hosted Laravel app.

---

## 2. Core UI Decision

Filament provides the application shell.

Vue owns the interactive page bodies.

Page loads should only happen when moving between major application sections. Once a section is loaded, Vue should load and refresh data through JSON APIs.

### Responsibility Split

| Area | Owner |
|---|---|
| Authentication | Laravel / Filament |
| Navigation | Filament |
| Page registration | Filament |
| Layout shell | Filament |
| Interactive page body | Vue |
| Data loading | Vue via JSON APIs |
| Data rendering | Vue |
| Live reactions | Vue via broadcast events |
| Payload inspection | Vue using JSON payloads |

Filament should not be used as the main interaction engine for schema browsing, table inspection, query exploration, or organ operation views.

The rule is:

> Filament gives the room. Vue furnishes the room. Laravel supplies JSON through the walls.

---

## 3. Payload Contract

All application data payloads should be JSON.

Avoid server-rendered HTML fragments, Blade partial payloads, or Livewire-heavy state for the main explorer experience.

The preferred contract is:

1. Vue requests JSON.
2. Laravel returns JSON.
3. Broadcasts announce that something changed or completed.
4. Vue decides what to reload.

---

## 4. Major View Families

Surface Viewer should be planned as multiple views over the same organism data, not as one expanding database viewer.

### 4.1 Database Schema View

Purpose:

> What exists?

This view shows structural information about observed databases.

Expected content:

- Observed database connections
- Schema snapshots
- Tables
- Columns
- Indexes
- Foreign key metadata
- Schema metadata
- Snapshot comparison later, if useful

Primary source tables:

- `database_connections`
- `database_schema_snapshots`
- `database_table_schemas`

Live update requirement:

- No broadcast required initially.
- Schema data can be loaded statically/on demand through JSON APIs.

---

### 4.2 Raw Table View

Purpose:

> What is actually in the table?

This view allows raw table contents to be inspected across all observed databases.

Expected content:

- Table rows
- Columns
- Pagination
- Search
- Filters
- Row detail inspection
- JSON expansion
- Copyable values

Primary data path:

- Read-only query executor
- Common JSON API surface
- Captured schema snapshots used as allowlists

Live update requirement:

- No broadcast required initially.
- Raw table views can be static/on demand.
- The user can refresh or change filters manually.

Important rule:

> Raw Table View should not care which observed database it is looking at.

The same read/query surface should support:

- `surface_viewer`
- `bloodstream`
- `subconscious`
- `impressions`
- `sidecar`

---

### 4.3 Query Lens View

Purpose:

> What does this data mean?

This view exposes useful, named queries that provide organism understanding.

These are not simply arbitrary SQL snippets. They are meaningful query lenses over organism state.

Examples:

- Recent unsensemade impressions
- Pending dreamstate requests
- Ready dreamstate candidates
- Active Bloodstream publishers
- Active Bloodstream subscriptions
- Failed sidecar email syncs
- Stale schema snapshots
- Messages waiting for routing

Potential source tables:

- `database_query_hooks`
- `database_saved_queries`
- `database_query_runs`

Live update requirement:

- Broadcast may be used later where a query lens represents active organism state.
- Query lenses should still fetch result payloads as JSON.

---

### 4.4 Organ Operation Views

Purpose:

> What is the organism doing right now?

These views react to organ operations and Bloodstream observations.

Expected pattern:

1. Organ operation happens.
2. Organ emits observation into Bloodstream.
3. Surface Viewer receives broadcast or mapped event.
4. Vue marks the relevant operation/query/view dirty or active.
5. Vue loads the current JSON payload through an API.

Live update requirement:

- Yes.
- Organ operations should use broadcast signals.
- Broadcasts should announce state changes, availability, or completion.
- Full payloads should be loaded through JSON APIs, not pushed wholesale through broadcasts by default.

---

### 4.5 Future Meaningful Surface Layouts

Purpose:

> What does this become for a human?

These are later product-facing layouts built on top of the schema, table, query, and operation foundations.

Examples:

- View markdown files rendered and edited
- View emails and their impressions
- Edit email address filters
- Edit domain filters
- View Bloodstream subscriptions
- View Bloodstream publishers

These are not raw database views. They are meaningful layouts that use the underlying query and observation foundation.

---

## 5. Broadcast Boundary

Broadcasting should be used where the system is actively changing and the user benefits from live awareness.

### Broadcast Not Required Initially

| View | Broadcast? | Reason |
|---|---:|---|
| Database Schema View | No | Schema does not need to pulse live |
| Raw Table View | No | Static/on-demand loading is enough |

### Broadcast Useful

| View | Broadcast? | Reason |
|---|---:|---|
| Organ Operation Views | Yes | Operations are eventful |
| Bloodstream Activity Views | Yes | Activity should be live-aware |
| Query Lens Views | Later / selective | Only where the lens represents active state |
| Future Surface Layouts | Selective | Depends on workflow |

Broadcasts should generally carry change signals, not full table payloads.

Preferred pattern:

1. Broadcast says something changed.
2. Vue receives the signal.
3. Vue fetches the relevant JSON payload.
4. Vue updates only the affected panel.

---

## 6. Bloodstream Role

Bloodstream should be treated as the primary live signal source for organ-owned activity.

The live update model is:

1. Organs observe their own important changes.
2. Organs publish observations into Bloodstream.
3. Surface Viewer listens to Bloodstream or receives mapped broadcasts.
4. Surface Viewer maps observations to affected views, query lenses, tables, or operations.
5. Vue reloads only the relevant JSON payload.

This avoids making Surface Viewer constantly poll every organ database.

Fallback polling can exist later, but it should not be the main live architecture.

---

## 7. Shared Foundation

The view families should be built on a shared foundation.

| Foundation Piece | Used By |
|---|---|
| Observed database registry | All database views |
| Schema snapshots | Schema view, raw table validation |
| Read-only query executor | Raw table view, query lenses |
| Query hooks / lenses | Meaningful query view, future layouts |
| Broadcast observation mapping | Organ operation views, Bloodstream views |
| Vue page bodies | All interactive views |
| Filament shell | Navigation, auth, layout |

The goal is one common data/query/live foundation with multiple views on top.

Do not build one schema viewer that slowly grows unrelated responsibilities.

---

## 8. Read-Only Query Surface

Raw table inspection and query lenses should use a guarded read-only query surface.

Important rules:

- Same query surface for all observed databases.
- Read-only by design.
- No inserts.
- No updates.
- No deletes.
- No schema mutation.
- No multi-statement SQL.
- No unbounded result sets.
- No passwords stored in the registry.
- No local topology values leaked into public config examples.

Captured schema snapshots should be used as allowlists for table and column selection.

---

## 9. Current Observed Database Context

Observed database connections are currently planned as:

- `surface_viewer`
- `bloodstream`
- `subconscious`
- `impressions`
- `sidecar`

Existing pipeline:

1. Observed DB connections are configured statically.
2. `olo:database-connections:sync` syncs configured connections into the local registry.
3. `olo:database-schemas:pull` captures schema snapshots.
4. Future Vue views consume schema, table, query, and operation JSON APIs.

---

## 10. Design Principle

Surface Viewer should separate four layers:

| Layer | Question |
|---|---|
| Schema Map | What exists? |
| Raw Data | What is in the table? |
| Query Lenses | What does it mean? |
| Surface Layouts | How should a human work with it? |

Each layer can use the same foundation without being collapsed into the same UI concept.

This keeps the system clear, expandable, and honest about what each view is for.

---

## 11. Locked Planning Notes

- Vue should own basically all interactive page bodies.
- Filament should primarily be shell, navigation, auth, and layout.
- Page loads should happen only where needed.
- Everything else should load through Vue and JSON APIs.
- Schema view does not need live broadcast updates initially.
- Raw table views can be static/on-demand initially.
- Organ operations should use broadcast signals.
- Broadcasts should trigger JSON reloads, not carry full rendered HTML.
- All payloads should be JSON.
- The work is still in planning mode.

