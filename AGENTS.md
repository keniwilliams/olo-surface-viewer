## Laravel Boundary Rules

Keep the codebase within plain Laravel boundaries. Do not invent dynamic framework layers inside Laravel.

### Controllers

Controllers are request coordinators only.

Controllers may:

* receive the request
* authorize through Policies or Gates
* validate through Form Requests
* call one model or one repository
* return an API Resource, response object, redirect, or view

Controllers must not:

* contain business logic
* build complex arrays for Vue
* parse payloads
* inspect database schemas
* perform multi-model orchestration directly
* contain repeated query logic

### Models

Models own data extraction from their own table or view.

Models may define:

* database connection
* table or view name
* primary key
* casts
* relationships
* scopes
* contract constants
* simple accessors
* named-column query scopes
* simple extraction from their own row data

Use models for single-model reads and single-row meaning.

Do not move single-model logic into a repository unless it is genuine data-access/query logic. Do not move single-model logic into a trait unless a second unrelated class also needs it.

### Repositories

Default pattern: a Repository (with Interface) sits in front of each Model that has non-trivial data-access logic.

Repositories may:

* hold named query methods (`findActive()`, `forDateRange()`, `forOwner()`)
* compose complex queries too involved for a simple model scope
* return typed models or collections

Repositories must not:

* contain cross-model orchestration
* contain business/workflow decisions
* perform runtime schema discovery
* guess available columns
* use generic mixed row adapters

A Repository is justified only when a model's data-access logic is complex or reused enough that a plain scope/accessor would clutter the model class. If the query is a single simple `where()`, keep it as a model scope. Do not create a Repository that only proxies a single trivial model query.

Controllers, Jobs, and Commands call Repositories directly. There is no Service layer by default.

### Interfaces

Every Repository is paired with an Interface. Consumers (Controllers, Jobs, Commands) type-hint the Interface, not the concrete Repository.

The Interface's only purpose is decoupling consumers from the concrete implementation.

If a Service is later introduced in front of a Repository, the Interface is dropped. The Service becomes the decoupling boundary instead — maintaining both is redundant.

### Services

Services are not the default pattern. Do not create a Service in front of a Repository unless a Repository already exists beneath it.

A Service exists only in front of an existing Repository, and only when introduced deliberately, not inferred from the number of models or repositories a piece of code happens to touch.

When a Service is introduced:

* it may combine results from multiple Repositories
* it may coordinate cross-database, queued, or external work
* it must not perform runtime schema discovery, guess columns, or use mixed-shape adapters
* the Repository Interface beneath it is removed

### Traits

A Trait is justified only when the same behavior is needed by two or more unrelated classes (unrelated meaning no shared parent it could live on instead).

Common valid uses:

* shared query shape across Repositories (date-range filtering, cursor pagination, owner scoping)
* shared Model behavior that isn't a relationship or cast (contract-version checks, status helpers)
* shared Repository boilerplate (`findOrFail`, `all`, `paginate`) reused across otherwise unrelated Repositories

Do not create a Trait for a single consumer. Inline the method; extract into a Trait only when a second unrelated class needs the same behavior.

### API Resources and Response Shape

Backend-normalized fields must be produced by API Resources, dedicated response transformers, or explicit presenter methods.

Prefer Laravel API Resources for HTTP responses.

Vue must receive stable, named fields. Vue must not infer database meaning, classify records, parse raw payloads, or derive state from IDs, schemas, source refs, or payload shape.

Controllers should not hand-build large response arrays when an API Resource or presenter is the correct boundary.

### Form Requests, Policies, Jobs, Events

Use standard Laravel primitives:

* Form Requests handle validation and request-specific input preparation.
* Policies and Gates handle authorization.
* Jobs handle async or queued work.
* Events and Listeners handle side effects after domain actions.
* Commands handle CLI entry points.

Do not hide validation, authorization, async work, or side effects inside models or Vue components.

### Database Access

Prefer Eloquent model queries.

Use named-column selects.

Avoid `DB::table()` and raw SQL unless:

* the query cannot reasonably be expressed through an existing model or repository
* the query is performance-critical and documented
* the raw query is isolated in a model-specific method or repository method
* the selected columns are explicit

Never use `SELECT *` in application queries.

### Runtime Schema Archaeology

Do not add runtime schema archaeology during normal application flow.

Forbidden during page rendering or API responses:

* table existence checks
* dynamic column discovery
* `pg_catalog` inspection
* schema introspection
* candidate column lists
* framework-within-the-framework defensive logic

The model, migration, tests, casts, and contract constants define the expected shape.

### Contracts

A contract is an explicit model-level expectation for a table, view, API payload, or cross-system projection.

When a table or view has a versioned contract, define the version on the model:

```php
public const CONTRACT_VERSION = '...';
```

Validate contract versions only where data crosses a boundary between systems or databases.

Invalid contract versions must use the standard unresolved fallback shape described in **Error and Fallback Shape**.

### Eager Loading and N+1

Avoid N+1 queries.

When rendering lists, batch data access.

Use eager loading, grouped queries, or batch lookups before mapping rows.

Do not query per card, per row, or per Vue component when the same data can be loaded once for the listing.

### Error and Fallback Shape

Fallbacks must be consistent.

For recoverable per-row failures, return normalized unresolved fields rather than throwing into the UI.

Use this pattern:

```php
[
    'resolved' => false,
    'resolution_error' => 'short safe reason',
]
```

Use domain-specific names where useful, for example:

```php
[
    'provenance_resolved' => false,
    'provenance_resolution_error' => 'no feed row found',
]
```

Do not swallow exceptions silently unless the returned fallback states what failed.

### Mixed-Shape Adapters

Do not use mixed-shape adapters when a typed model should be used.

Avoid:

* generic `rowValue()` helpers
* generic `stringValue()` helpers
* array/object/model fallback readers
* dynamic field-name guessing
* candidate column lists

Prefer typed models, casts, accessors, scopes, and explicit DTO/resource fields.

### Review Judgement Rules

Some boundary rules cannot be fully enforced by static analysis.

These require PR review judgement:

* Whether a repository method belongs on the model instead as a simple accessor/scope.
* Whether a trait is genuinely shared or premature (used by only one class).
* Whether a raw query truly cannot reasonably be expressed through an existing repository or model.
* Whether a class has grown large because the domain is genuinely complex or because unrelated concerns have accumulated.

Use this review rule:

If a repository or model has grown beyond a clear single purpose, split it or move logic to where it actually belongs.

As a warning sign, review any class that has:

* more than one clear responsibility
* unrelated model groups
* repeated private helper chains
* mixed read/write responsibilities
* logic that can be named more clearly as a model scope, accessor, cast, policy, job, listener, or API Resource

### Enforcement

Architecture rules should be enforced where practical.

Use:

* PHPStan for type correctness
* architecture tests or dependency rules for layer boundaries
* tests for query count and N+1-sensitive paths
* PR review checklist for controller/repository/model boundaries

At minimum, new code must not introduce:

* runtime schema inspection
* large controller response shaping
* Vue-side database inference
* `SELECT *`
* per-row queries in listing views
* generic mixed-shape adapters
* a Service class created without an existing Repository beneath it
* a Repository created for a single trivial model query
* a Trait created for a single consumer
