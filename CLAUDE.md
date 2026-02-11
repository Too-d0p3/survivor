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
- **AiLog** — AI interaction audit trail (prompt, response, duration, metadata)
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

## Key Entities and Relationships

- **User** → owns many **Games**
- **Game** → has many **Players** (sandbox mode flag)
- **Player** → has many **PlayerTraits** (name, description, user_controlled flag)
- **PlayerTrait** → links Player to **TraitDef** with strength (0.0–1.0)
- **TraitDef** — key (unique), label, description, type (social/strategic/emotional/physical)
- **AiLog** — full audit of every AI interaction
