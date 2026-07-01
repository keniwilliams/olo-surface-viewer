# OLO Surface Laravel + Filament Planning Note

**Status:** Planning note  
**Date:** 2026-07-01  
**Next session target:** Start implementation shape on 2026-07-02  
**Scope:** OLO Surface as Laravel/Filament operator view and administration layer

---

## 1. Core Decision

OLO Surface can be rebuilt as a Laravel system, primarily for views and administration.

Laravel will provide:

- Operator-facing views
- Database administration views
- Contract definition management
- Filament panels and resources
- Separated organ-specific screens
- Local-first development workflow
- Later deployment through Coolify if needed

Laravel will not become the behavioural authority of OLO.

---

## 2. Authority Split

The intended split is:

```text
Laravel manages contract definitions.
Bloodstream enforces contract behaviour.
Organs consume contracts through their own boundaries.
```

This means Laravel Surface can define, display, edit, and administer contract records, but Bloodstream remains responsible for enforcing contract behaviour at runtime.

Surface is the cockpit, not the bloodstream.

---

## 3. Planned System Direction

The working sequence is:

1. Use Bloodstream first and create views of activity.
2. While doing that, build the vascular system for Bloodstream as previously planned.
3. Configure access to all organ databases.
4. Create usable views over organ databases.
5. Pipe the morphogenesis trios into the organs.
6. Wire Surface views for organs.
7. Recreate the existing Surface, Impressions, Sidecar, and Dreamstate interfaces inside Laravel/Filament.

---

## 4. Laravel Surface Responsibilities

Laravel Surface should handle:

- Bloodstream activity views
- Bloodstream contract definitions
- Contract administration
- Impressions record views
- Dreamstate run/candidate/return-packet views
- Sensemaker request/completion/failure views
- Sidecar interface recreation
- Organ database browsing
- Morphogenesis trio visibility
- Human/operator workflows
- Admin CRUD over safe read/write models

---

## 5. Non-Responsibilities

Laravel Surface should not own:

- Bloodstream runtime enforcement
- Organ-internal behaviour
- Sensemaker processing decisions
- Impressions ingestion behaviour
- Dreamstate execution behaviour
- Global governance over organs
- Hidden routing authority

Any mutation from Surface should remain explicit and bounded.

---

## 6. Frontend Direction

The frontend priority is:

- Quick to set up
- Low maintenance
- Artisan/scaffold friendly
- Good for database-backed views
- Good for small agent work
- Each view separated well
- Avoid one large frontend bundle where possible
- Prefer many small panels/views over one large SPA

Chosen direction:

```text
Laravel + Filament panel installer
No templates
No heavy SaaS starter kit
```

---

## 7. Filament Decision

Use the Filament panel installer directly rather than a prebuilt template.

Reasoning:

- Avoid template sludge
- Keep the architecture clean
- Let OLO-specific boundaries shape the panels
- Better for small-model agent edits
- Easier to reason about generated resources
- Less inherited opinionated code

Candidate shape:

```text
surface/
├─ bloodstream/
│  ├─ activity
│  ├─ contracts
│  └─ pressure
├─ impressions/
│  ├─ records
│  ├─ sensemade
│  └─ routing
├─ dreamstate/
│  ├─ runs
│  ├─ candidates
│  └─ return-packets
├─ sensemaker/
│  ├─ requests
│  ├─ completions
│  └─ failures
└─ organs/
   ├─ reflector
   ├─ express
   └─ builder
```

---

## 8. Hosting Direction

Initial hosting direction:

```text
Local Laravel app
  ↓
Local browser access
  ↓
Optional Cloudflare Tunnel
```

Later hosting direction:

```text
Git repo
  ↓
Coolify
  ↓
Nixpacks
  ↓
Cloudflare
```

The local-first path is preferred at the start to avoid deployment drag while the Surface shape is still being discovered.

---

## 9. Nixpacks Understanding

Nixpacks is a build strategy that turns a source repo into a container image automatically.

It is not the application host itself.

The split is:

```text
Coolify runs the application.
Nixpacks builds the container image.
Cloudflare exposes access.
Laravel provides the application.
```

Use Nixpacks first when moving to Coolify. Only move to a Dockerfile later if Nixpacks becomes limiting.

---

## 10. Local Hosting Plan

For the first local version:

```text
Windows machine
  ↓
Laravel app running locally
  ↓
http://127.0.0.1:8000
  ↓
optional Cloudflare Tunnel
```

Likely development loop:

```text
php artisan serve
npm run dev
local Postgres
Cloudflare Tunnel only when needed
```

Recommended starting database mode:

- Use Postgres for Surface rather than SQLite once real organ/database views begin.
- SQLite is acceptable only for the earliest bootstrapping step.

---

## 11. Boundary Rule

Direct local hosting is acceptable while Surface is mostly read-only.

Once Surface can mutate important records, such as contract definitions, routing state, organ access configuration, admin actions, or Bloodstream settings, it should move behind a cleaner operational boundary.

Principle:

```text
Prototype directly.
Operate through Coolify.
```

---

## 12. Tomorrow Starting Point

Next practical starting point:

1. Create a clean Laravel Surface project.
2. Install Filament using the panel installer.
3. Keep the initial app local-only.
4. Create the first Bloodstream activity view conceptually before wiring all organ databases.
5. Keep all views small and separated.
6. Avoid templates and broad SPA decisions.
7. Do not move enforcement into Laravel.

---

## 13. Current Planning Status

Locked:

- Laravel is suitable for Surface.
- Filament panel installer is preferred.
- No templates for now.
- Surface is primarily views and administration.
- Bloodstream remains enforcement authority.
- Local hosting first is acceptable.
- Coolify + Nixpacks remains the later deployment path.

Open:

- Exact project path/name.
- First Bloodstream activity view schema.
- Which database connection becomes primary for Surface.
- How many Filament panels to start with.
- Whether organ views are separate panels or grouped resources.

