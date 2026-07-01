# OLO Surface Viewer Architecture Plan

**Status:** Planning  
**Repo:** `D:\OLO-Software\olo-surface-viewer`  
**Application:** OLO Surface Viewer  
**Stack:** Laravel, Filament shell, Vue page bodies, JSON APIs

---

## 1. Purpose

OLO Surface Viewer is not a database administration tool.

It is the inspection and interaction surface for the OLO organism.

The application should help the user understand what the organism knows, what it is doing, what changed, what is stuck,
what is being filtered, and what human-facing surfaces need attention.

Surface Viewer should not duplicate IDE or database-client capabilities. Raw database browsing, ad hoc table inspection,
and generic row viewing are already better handled by existing development tools.

The architecture direction is deliberately Vue-first inside a Filament-hosted Laravel app.

---

## 2. Core UI Decision

Filament provides the application shell.

Vue owns the interactive page bodies.

Page loads should only happen when moving between major application sections. Once a section is loaded, Vue should load
and refresh data through JSON APIs.

### Responsibility Split

| Area                  | Owner                    |
|-----------------------|--------------------------|
| Authentication        | Laravel / Filament       |
| Navigation            | Filament                 |
| Page registration     | Filament                 |
| Layout shell          | Filament                 |
| Interactive page body | Vue                      |
| Data loading          | Vue via JSON APIs        |
| Data rendering        | Vue                      |
| Live reactions        | Vue via broadcast events |
| Payload inspection    | Vue using JSON payloads  |

Filament should not be used as the main interaction engine for schema context, query exploration, organ operations, or
later meaningful Surface layouts.

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

Broadcasts should generally carry change signals, not full rendered content and not large table payloads.

---

## 4. Explicit Non-Goal: Raw Database Viewing

Raw database viewing is intentionally excluded.

Surface Viewer should not provide a generic table browser, generic row inspector, or generic ad hoc SQL interface.

Reasons:

- Existing IDEs and database clients already handle raw table browsing better.
- Rebuilding that capability inside Surface Viewer would consume time without adding organism-specific value.
- A generic database browser would pull the product toward being a weaker database administration tool.
- Surface Viewer should focus on meaning, operations, observations, filters, impressions, and purpose-built layouts.

Excluded from the plan:

- Generic raw table browsing
- Generic row-detail inspection
- Generic database search/filter UI
- Ad hoc SQL workbench
- Recreating IDE database tooling

The boundary is:

> Surface Viewer should not answer “what rows are in this table?” unless that question is part of a meaningful organism
> lens or purpose-built Surface layout.

---

## 5. Major View Families

Surface Viewer should be planned as multiple views over organism meaning and operation, not as one expanding database
viewer.

### 5.1 Schema Context View

Purpose:

> What structure does this organism expose?

This view shows bounded structural information about observed databases.

Expected content:

- Observed database connections
- Schema snapshots
- Tables
- Columns
- Indexes
- Foreign key metadata
- Schema metadata
- Snapshot comparison later, if useful
- Relationship between schema objects and known query lenses, where useful

Primary source tables:

- `database_connections`
- `database_schema_snapshots`
- `database_table_schemas`

Live update requirement:

- No broadcast required initially.
- Schema data can be loaded statically or on demand through JSON APIs.

Boundary:

- This is schema context, not a table browser.
- It exists to support understanding of organism structure, not to replace IDE database inspection.

---

### 5.2 Query Lens View

Purpose:

> What does this data mean?

This view exposes useful, named queries that provide organism understanding.

These are not arbitrary SQL snippets. They are meaningful lenses over organism state.

Examples:

- Recent impressions awaiting sensemaking
- Pending dreamstate requests
- Ready dreamstate candidates
- Active Bloodstream publishers
- Active Bloodstream subscriptions
- Failed sidecar email syncs
- Stale schema snapshots
- Messages waiting for routing
- Email filters affecting routing
- Markdown files with impressions

Potential source tables:

- `database_query_hooks`
- `database_saved_queries`
- `database_query_runs`

Live update requirement:

- Broadcast may be used where a query lens represents active organism state.
- Query lenses should fetch result payloads as JSON.
- Broadcasts should mark relevant lenses dirty or trigger a JSON reload.

Boundary:

- Query lenses may read database data, but they must expose meaning, not generic table contents.
- If the same job can be done better in an IDE, it does not belong here.

---

### 5.3 Organ Operation Views

Purpose:

> What is the organism doing right now?

These views react to organ operations and Bloodstream observations.

Expected pattern:

1. Organ operation happens.
2. Organ emits observation into Bloodstream.
3. Surface Viewer receives a broadcast or mapped event.
4. Vue marks the relevant operation, query lens, or view dirty or active.
5. Vue loads the current JSON payload through an API.

Expected content:

- Running or recent organ operations
- Operation outcomes
- Failed or stuck operations
- Observation payload summaries
- Related query lenses
- Human action hints where appropriate

Live update requirement:

- Yes.
- Organ operations should use broadcast signals.
- Broadcasts should announce state changes, availability, or completion.
- Full payloads should be loaded through JSON APIs, not pushed wholesale through broadcasts by default.

---

### 5.4 Bloodstream Visibility Views

Purpose:

> Which parts of the organism are speaking, listening, and moving signals?

Expected content:

- Bloodstream subscriptions
- Bloodstream publishers
- Recent observations
- Observation subjects
- Event flow between organs
- Mapping from observations to Surface views or query lenses

Live update requirement:

- Yes for active observation streams.
- JSON APIs should provide current state and history.
- Broadcasts should signal new observations or changed activity.

Boundary:

- This is organism visibility, not a raw message dump by default.
- Raw payload inspection can exist when needed, but the main value is understanding flow and responsibility.

---

### 5.5 Future Meaningful Surface Layouts

Purpose:

> What does this become for a human?

These are later product-facing layouts built on top of schema context, query lenses, Bloodstream observations, and organ
operation payloads.

Examples:

- View markdown files rendered and edited
- View emails and their impressions
- Edit email address filters
- Edit domain filters
- View Bloodstream subscriptions
- View Bloodstream publishers

These are not raw database views. They are meaningful layouts that use the underlying query and observation foundation.

Example distinction:

- A database tool can show an `emails` table.
- Surface Viewer should show an email, its impressions, routing/filter context, related observations, and useful
  actions.

---

## 6. Broadcast Boundary

Broadcasting should be used where the system is actively changing and the user benefits from live awareness.

### Broadcast Not Required Initially

| View                          | Broadcast? | Reason                             |
|-------------------------------|-----------:|------------------------------------|
| Schema Context View           |         No | Schema does not need to pulse live |
| Static reference/config views |         No | On-demand JSON loading is enough   |

### Broadcast Useful

| View                         | Broadcast? | Reason                                      |
|------------------------------|-----------:|---------------------------------------------|
| Organ Operation Views        |        Yes | Operations are eventful                     |
| Bloodstream Visibility Views |        Yes | Activity should be live-aware               |
| Query Lens Views             |  Selective | Only where the lens represents active state |
| Future Surface Layouts       |  Selective | Depends on workflow                         |

Preferred broadcast pattern:

1. Broadcast says something changed.
2. Vue receives the signal.
3. Vue fetches the relevant JSON payload.
4. Vue updates only the affected panel.

---

## 7. Bloodstream Role

Bloodstream should be treated as the primary live signal source for organ-owned activity.

The live update model is:

1. Organs observe their own important changes.
2. Organs publish observations into Bloodstream.
3. Surface Viewer listens to Bloodstream or receives mapped broadcasts.
4. Surface Viewer maps observations to affected views, query lenses, or operations.
5. Vue reloads only the relevant JSON payload.

This avoids making Surface Viewer constantly poll every organ database.

Fallback polling can exist later only if a valuable view has no useful observation stream, but it should not be the main
live architecture.

---

## 8. Shared Foundation

The view families should be built on a shared foundation.

| Foundation Piece                | Used By                                                         |
|---------------------------------|-----------------------------------------------------------------|
| Observed database registry      | Schema context and known data-source mapping                    |
| Schema snapshots                | Schema context and query lens validation                        |
| Meaningful query hooks / lenses | Query Lens View and future layouts                              |
| Query run records               | Query history, debugging, and lens usefulness                   |
| Broadcast observation mapping   | Organ operation views, Bloodstream views, selected query lenses |
| Vue page bodies                 | All interactive views                                           |
| Filament shell                  | Navigation, auth, layout                                        |

The goal is one common meaning/query/live foundation with multiple views on top.

Do not build one schema viewer that slowly grows unrelated responsibilities.

---

## 9. Query Surface Boundary

Surface Viewer may need read-only database access to power query lenses and meaningful layouts.

That access should be guarded and purpose-driven.

Important rules:

- Same internal query mechanism may support all observed databases.
- Read-only by design.
- No inserts.
- No updates.
- No deletes.
- No schema mutation.
- No multi-statement SQL.
- No unbounded result sets.
- No passwords stored in the registry.
- No local topology values leaked into public config examples.
- No generic raw database browser should be built on top of this.

Captured schema snapshots may be used as allowlists for query-lens table and column selection.

The query surface exists to support meaningful lenses and future Surface layouts, not to expose generic database
browsing.

---

## 10. Current Observed Database Context

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
4. Future Vue views consume schema context, query lens, Bloodstream, and operation JSON APIs.

---

## 11. Design Principle

Surface Viewer should separate four value layers:

| Layer            | Question                                 |
|------------------|------------------------------------------|
| Schema Context   | What structure does the organism expose? |
| Query Lenses     | What does the organism data mean?        |
| Organ Operations | What is happening right now?             |
| Surface Layouts  | How should a human work with this?       |

Raw database viewing is intentionally not one of the value layers.

This keeps the system clear, useful, and focused on value beyond existing IDE/database tooling.

---

## 12. Locked Planning Notes

- Vue should own basically all interactive page bodies.
- Filament should primarily be shell, navigation, auth, and layout.
- Page loads should happen only where needed.
- Everything else should load through Vue and JSON APIs.
- Schema context does not need live broadcast updates initially.
- Raw database/table viewing is excluded and should not be added back by default.
- Organ operations should use broadcast signals.
- Bloodstream observations should be the primary live signal source for organ-owned activity.
- Broadcasts should trigger JSON reloads, not carry full rendered HTML.
- All payloads should be JSON.
