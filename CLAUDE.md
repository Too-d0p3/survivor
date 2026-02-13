# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AI Survivor Simulation — an experimental text-graphic game inspired by the "Survivor" reality show. AI-driven characters have simulated personalities, memories, and evolving relationships (real vs. perceived). Built as an auditable and tunable sandbox simulation.

## Tech Stack

- **Backend:** PHP 8.5+, Symfony 8.0, Doctrine ORM, PostgreSQL 18.1
- **Frontend:** Nuxt 3 (SSR disabled), Nuxt UI 3 (Radix Vue based), Pinia, VueUse
- **AI:** Custom `AiClient` in Symfony + `google-gemini-php/client`
- **Infrastructure:** Docker Compose (PHP-FPM, Nginx, PostgreSQL, Adminer, pgAdmin)

## Common Commands

### Infrastructure
```bash
docker-compose up -d
```

### Backend (from `backend/`)
```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console app:sample-data:create    # Seed traits, users (admin@admin.cz / admin123)
php bin/console make:migration            # After entity changes
```

### Frontend (from `frontend/`)
```bash
npm install
npm run dev                               # Dev server on localhost:3000
npm run build
npm run generate-client                   # Regenerate OpenAPI client types
```

### Combined migration shortcut (from `frontend/`)
```bash
npm run migrate    # Makes migration, runs it, regenerates client types
```

### Access Points
- Frontend: http://localhost:3000
- API (Nginx): http://localhost:8000
- Adminer: http://localhost:8080
- pgAdmin: http://localhost:5050 (admin@local.com / admin)

## Architecture

### Backend — Domain-Driven Design

Code is organized into domains under `backend/src/Domain/`:
- **Ai** — AI client, logging, trait inference services
- **Game** — Core game loop and state management
- **Player** — Character entities, traits, relationship states
- **TraitDef** — Personality trait definitions (social/strategic/emotional/physical types)
- **User** — Authentication (JWT via LexikJWTAuthenticationBundle)

Shared utilities live in `backend/src/Shared/` (base controller, sample data system).

### Strict Controller → Facade → Service Pattern

1. **Controller** — Request/Response only. Dispatches to Facade.
2. **Facade** — Infrastructure boundary. Only layer that touches Doctrine, filesystem, external APIs. Fetches entities by ID, obtains current time, passes data to Services. Calls `$em->flush()` at end.
3. **Service** — Pure business logic. No infrastructure dependencies. Receives processed data/entities/time from Facade. If infrastructure is needed in a loop, Facade passes a closure.

### Frontend Structure

- `frontend/pages/` — Route pages (`/admin`, `/game`, `/login`)
- `frontend/components/` — Vue components organized by domain
- `frontend/stores/` — Pinia state stores

## PHP Coding Standards

See [CODING_STANDARDS.md](CODING_STANDARDS.md) for the full backend coding standards.

**PHPCS is mandatory.** Every commit must pass `composer cs:check` with zero errors. After any PHP file change, always verify by running:
```bash
docker-compose exec php composer cs:check
```
If there are auto-fixable violations, run `docker-compose exec php composer cs:fix` first, then verify. Never commit code that fails PHPCS.

**PHPStan is mandatory.** Every commit must pass `composer stan` with zero errors. After any PHP file change, always verify by running:
```bash
docker-compose exec php composer stan
```
PHPStan runs at level max with strict rules. Existing violations are captured in `phpstan-baseline.neon` — new code must not introduce new errors. To regenerate the baseline (e.g. after fixing baselined errors), run `docker-compose exec php composer stan:baseline`.

## Testing

**Tests are mandatory.** Every new feature, bug fix, or code change must include corresponding tests. Every commit must pass `composer test` with zero errors.

### Test Infrastructure

- **PHPUnit 12** — test runner
- **DAMA DoctrineTestBundle** — wraps each test in a DB transaction and rolls back after (fast isolation)
- **Test database:** `survivor_test` (auto-suffixed by Doctrine `when@test`)

### Test Types and Structure

```
backend/tests/
├── Unit/           # Pure logic tests — no DB, no mocks, no kernel
│   └── Domain/
│       └── {Domain}/{Service|Entity}Test.php
├── Integration/    # Real DB tests via KernelTestCase + DAMA rollback
│   ├── AbstractIntegrationTestCase.php
│   └── Domain/
│       └── {Domain}/{Facade}Test.php
└── Functional/     # HTTP tests via WebTestCase
    ├── AbstractFunctionalTestCase.php
    └── Domain/
        └── {Domain}/{Controller}Test.php
```

| Layer | Test type | Base class | Mocks allowed? |
|-------|-----------|------------|----------------|
| Entity | Unit | `TestCase` | Never |
| Service | Unit | `TestCase` | Never — Services are pure, use real entities |
| Facade | Integration | `AbstractIntegrationTestCase` (`KernelTestCase`) | Only AiClient (external API) |
| Controller | Functional | `AbstractFunctionalTestCase` (`WebTestCase`) | Only AiClient |

### Running Tests

```bash
docker-compose exec php composer test              # All tests
docker-compose exec php composer test:unit          # Unit only (fast, no DB)
docker-compose exec php composer test:integration   # Integration (with DB)
docker-compose exec php composer test:functional    # Functional (HTTP + DB)
docker-compose exec php composer qa                 # PHPCS + PHPStan + all tests
```

### Test Naming Convention

`test[MethodName][Scenario]` — e.g. `testDeleteGameWhenUserIsNotOwnerThrowsException`

### What Must Be Tested

Every new piece of code requires tests:
1. **New Service method** → unit test in `tests/Unit/Domain/{Domain}/`
2. **New Facade method** → integration test in `tests/Integration/Domain/{Domain}/`
3. **New Entity** → unit test for constructor, collection methods, and business methods
4. **New Controller endpoint** → functional test in `tests/Functional/Domain/{Domain}/`

### Test Database Setup (one-time)

```bash
docker-compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker-compose exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

## Commit Messages

- **Subject line only** — no commit description/body
- **Imperative mood** — e.g. "add player endpoint", not "added player endpoint"
- **No abbreviations** — use full words (e.g. "introduce" not "intro", "configuration" not "config")
- **Lowercase** — do not capitalize the first letter
- **No Co-authored-by**

## Agent Workflow

When implementing a new feature, follow this pipeline:

1. **spec-validator** — Clarify requirements: ask questions, flag ambiguities, produce a Feature Brief. Blocks if critical info is missing.
2. **architect** — Design the feature: use-case spec, API contract, method signatures, domain errors, Result objects. Produces the blueprint.
3. **entity-persistence-architect** — If entities/schema change: design entities, migrations, repository methods, indexes.
4. **prompt-architect** — If AI/LLM interaction needed: design prompt spec, input/output contracts.
5. **implementer** — Implement the code following architect specs. Run PHPCS + PHPStan. **Must write tests for all new code.**
6. **test-qa-engineer** — Review test coverage, add missing tests, write edge-case and boundary tests.
7. **code-review-gatekeeper** — Final review before commit. Block any violations, **including missing tests.**

For small changes (bug fixes, minor tweaks): skip spec-validator and architects, go directly to implementer → code-review-gatekeeper. **Tests are still mandatory even for small changes.**

### Critical Patterns All Agents Must Follow

These rules come from CODING_STANDARDS.md and must be enforced by every agent in the pipeline:

1. **Facade uses repository `get*()` methods** — Repository `get*()` throws a domain exception when entity is not found. Facade never calls `find*()` + manual null check. The not-found logic lives in the Repository, not the Facade.
2. **Facade does not proxy single repository calls** — If a Facade method only wraps a single repository read with no orchestration, it should not exist. Facades are for multi-step operations.
3. **Service methods are named after the use-case action** — `deleteGame()`, `startRound()`, `assignTraits()` — not after internal steps like `validateOwnership()` or `checkPermissions()`.
4. **Service methods return the entity or a Result object** — Never `void`. The Facade needs the result to continue orchestrating (persist, flush, return to Controller).
5. **UUID everywhere below Controller** — Facade, Service, Repository, and Exceptions all use `Symfony\Component\Uid\Uuid` for entity identifiers. Controller converts `string` → `Uuid` via `Uuid::fromString($id)` at the HTTP boundary. No raw `string` IDs below the Controller layer.
6. **Every code change must have tests** — No code is committed without corresponding tests. Unit tests for Services/Entities, integration tests for Facades, functional tests for Controllers. `composer qa` (PHPCS + PHPStan + tests) must pass before every commit.

## Key Entities and Relationships

- **User** → owns many **Games**
- **Game** → has many **Players** (sandbox mode flag)
- **Player** → has many **PlayerTraits** (name, description, user_controlled flag)
- **PlayerTrait** → links Player to **TraitDef** with strength (0.0–1.0)
- **TraitDef** — key (unique), label, description, type (social/strategic/emotional/physical)

**IGNORE FRONTEND CODE** — focus on backend for now. Frontend is basic and will be refactored later.
