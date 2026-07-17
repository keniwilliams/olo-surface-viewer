## Laravel Boundary Rules

Keep the codebase within plain Laravel boundaries. Do not invent dynamic framework layers inside Laravel.

### Framework Primitives First

Before writing custom logic, check whether Laravel already provides it: helpers (`str()`, `Arr::`, `Number::`, `now()`, etc.), Facades (`Context`, `Process`, `Bus`, `Concurrency`, `Cache`, `Http`), built-in Middleware, validation rules, collection methods, and Artisan primitives.

Do not roll out a custom implementation of something Laravel already ships, unless the built-in version genuinely cannot do the job, and that gap is stated explicitly.

This applies across every section below: Middleware, Process, Context, Bus, and collection/helper usage are not optional conventions, they are the default until proven insufficient for the specific case.

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

### Collections

Prefer Laravel Collections (`collect()`, Eloquent collections, `LazyCollection` for large sets) over hand-rolled array loops for filtering, mapping, grouping, reducing, or transforming data.

Do not write manual `foreach` loops building up arrays when a collection method (`map`, `filter`, `groupBy`, `pluck`, `reduce`, `each`, `chunk`, etc.) already expresses the same operation.

Use `LazyCollection` for large datasets or streaming reads (e.g. large CLI output, large query results) instead of loading everything into memory and iterating manually.

This is the same Framework Primitives First rule applied specifically to data transformation: a custom loop is only justified when a collection method genuinely cannot express the operation, and that gap is stated explicitly.

### Middleware

Middleware handles request-level, cross-cutting concerns that must run before a request reaches a Controller: authentication checks, rate limiting, CORS, locale detection, maintenance mode, request/response header manipulation.

Use Laravel's built-in middleware and standard registration (route middleware, middleware groups, `handle()`) for this. Do not reimplement what Laravel already provides as raw PHP inline in a Controller — for example, manually checking headers, tokens, or throttling counters at the top of a Controller method instead of using or writing Middleware.

If the same request-level check is being written or copy-pasted into more than one Controller, it belongs in Middleware, not in the Controllers.

Middleware must not:

* contain business logic
* make model-specific or single-resource authorization decisions (that's a Policy/Gate, called from the Controller)
* perform validation of request body content (that's a Form Request)
* query multiple models or orchestrate domain logic

Middleware's job stops at deciding whether the request should reach the application at all, or at transforming the request/response envelope. Anything past that boundary is a Controller/Policy/Form Request concern.

### External CLI Processes

Some work requires shelling out to an external CLI (build tools, AI agent CLIs, data processors, etc.) rather than calling an API directly.

Use the Laravel `Process` facade as the only invocation point. Do not use `shell_exec`, `exec`, `proc_open`, or Symfony Process directly.

Structure:

* **Command** — the CLI entry point when the invocation is user- or schedule-triggered.
* **Job** — the actual `Process::run()` call. External CLI calls are treated as async by default: they may run for seconds to minutes, so they belong in a queued Job, not inline in a Controller or Command action.
* **Repository** — only if the CLI's output needs to be persisted and queried later (e.g. session state, run history). Follows the same justification rule as any other Repository: not created for a single trivial read/write.
* **Event** — if the CLI's completion needs to trigger downstream side effects, fire an Event from the Job rather than chaining logic inline.

Parsing CLI output:

* Parse structured output (JSON, etc.) into a typed DTO or pass it directly to a Repository method.
* Do not build a generic adapter that guesses at output shape. This is the same violation as a Mixed-Shape Adapter applied to CLI output instead of a database row.
* If the CLI's output format is unstructured text, isolate the parsing in one method on the Job or a dedicated parser class, not spread across callers.

Concurrency and working directory:

* If multiple invocations of the same CLI can run in parallel against the same repository or filesystem state, isolate each invocation's working directory. Do not let concurrent Jobs share mutable working state.
* Concurrency limits are a queue-level concern (queue worker limits, not application code).

Do not:

* call an external CLI synchronously from a Controller action
* invoke the CLI through anything other than the `Process` facade
* build a mixed-shape adapter to normalize CLI output
* let a Job assume exclusive access to shared working directory state without isolating it

### AI Agent Orchestration

Use `laravel/ai` (or equivalent first-party AI package) for calling AI provider APIs directly. This is distinct from External CLI Processes above — no CLI binary, no `Process` facade, no CLI output parsing.

Structure:

* **Job** — a single agent invocation. One Job per agent call, same as any other async unit of work.
* **Parallel agents** — two distinct cases:
  * Results needed back within the current process before continuing (e.g. fan out to several agents, merge results, then proceed) → `Concurrency::run()`. Runs closures in parallel and blocks until all return.
  * Independent background work, no blocking wait needed → `Bus::batch()` of agent Jobs, handled via batch completion callbacks.
  * Do not hand-roll parallel execution with manual process/thread management in either case.
* **Sequential agents** — Job chains (`->chain()`), where one agent's output feeds the next Job's input. Do not chain agent calls synchronously inside a single Job.
* **Repository** — agent state or session persistence, same justification rule as any other Repository.
* **Service** — cross-agent coordination logic: deciding which agents run in what order, merging or routing results across a batch or chain. This is a legitimate Service use case under the existing Services rule, extended to front Jobs/Batches instead of Repositories. Do not put orchestration logic in a Job itself; a Job executes one agent call, a Service decides the shape of the orchestration.
* **Tool definitions** — one class per tool the agent can call. Do not inline tool logic into the agent call site.
* **Cross-job/agent shared data** (trace IDs, session IDs, orchestration metadata) — use the `Context` facade, not custom constructor arguments or manual payload threading. Context set before a batch or chain is dispatched is automatically captured and rehydrated into each Job, including across the queue boundary. Do not manually pass tracing/correlation data through Job constructors when Context already carries it.

Do not:

* orchestrate multiple agents via `Process` calls to a CLI binary when a direct API call via `laravel/ai` is available
* build custom parallel/sequential execution logic when `Bus::batch()` or Job chains already provide it
* thread trace/correlation/session data manually through Job constructors or payloads when `Context` already propagates it across the queue boundary
* parse agent output with a mixed-shape adapter instead of the package's typed response objects

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
