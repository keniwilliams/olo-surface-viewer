## Laravel Boundary Rules

Keep the codebase within plain Laravel boundaries. Do not invent dynamic framework layers inside Laravel.

### Controllers

Controllers are request coordinators only.

Controllers may:

* receive the request
* authorize through Policies or Gates
* validate through Form Requests
* call one model or one service
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

Do not move single-model logic into a service unless it coordinates with another model, database, queue, API, or external source.

### Services

Services exist only to coordinate work across multiple models, databases, queues, APIs, or external sources.

Services may:

* combine model results
* coordinate cross-database reads
* coordinate queued or external work
* return normalized backend data

Services must not:

* perform runtime schema discovery
* guess available columns
* use generic mixed row adapters
* replace model scopes/accessors
* become dumping grounds for single-model logic
* hide validation or authorization
* contain unrelated feature logic under a broad service name

If logic only concerns one model/table/view, prefer a model scope, accessor, cast, or explicit model method.

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

* the query cannot reasonably be expressed through an existing model
* the query is performance-critical and documented
* the raw query is isolated in a model-specific method or clearly named service method
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

* Whether a service name is too broad for the logic it contains.
* Whether a service mixes unrelated feature concerns.
* Whether a raw query truly cannot reasonably be expressed through an existing model.
* Whether a class has grown large because the domain is genuinely complex or because unrelated concerns have accumulated.

Use this review rule:

If a service coordinates more than one domain concern, or grows beyond a clear single purpose, split it or move single-model logic back to the relevant model.

As a warning sign, review any service that has:

* more than one public orchestration method
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
* PR review checklist for controller/service/model boundaries

At minimum, new code must not introduce:

* runtime schema inspection
* large controller response shaping
* Vue-side database inference
* `SELECT *`
* per-row queries in listing views
* generic mixed-shape adapters
