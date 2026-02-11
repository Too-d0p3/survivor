# 💎 Gemini CLI Project Context: AI Survivor Simulation

This file provides context and instructions for AI agents working on the AI Survivor Simulation project.

## 🌟 Project Overview

**AI Survivor Simulation** is an experimental text-graphic game inspired by the "Survivor" reality show. It features AI-driven characters with simulated personalities, memories, and evolving relationships (real vs. perceived). The goal is to create an auditable and tunable sandbox simulation.

### Core Stack
- **Backend:** PHP 8.5+ with **Symfony 8.0**, Doctrine ORM, and PostgreSQL.
- **Frontend:** **Nuxt 3** (Client-side only) with **Nuxt UI** (Radix Vue based).
- **AI Gateway:** Custom `AiClient` in Symfony, currently targeting a local LLM endpoint (e.g., LM Studio/Ollama) but equipped for OpenAI, Gemini, etc.
- **Infrastructure:** Docker Compose for the backend services and database.

---

## 🏗️ Architecture & Key Components

### Backend (`/backend`)
- **Domain-Driven Design:** Logic is organized into domains under `src/Domain/`:
    - `Ai`: AI Client, logging, and specific services for trait inference.
    - `Game`: Core game loop and state management.
    - `Player`: Character entities, traits, and relationship states.
    - `TraitDef`: Definitions for personality traits.
    - `User`: Authentication and user management.
- **AI Logging:** Every AI interaction is logged in the `ai_log` table for debugging and auditing (`AiLog` entity).
- **Sample Data:** Custom sample data system in `src/Shared/SampleData/` for seeding the simulation.

### Frontend (`/frontend`)
- **Framework:** Nuxt 3 (SSR disabled).
- **UI Components:** Uses `@nuxt/ui` for a modern look and feel.
- **State Management:** Pinia stores (e.g., `stores/game.ts`).
- **Pages:**
    - `/admin`: Management of users and trait definitions.
    - `/game`: The simulation interface.
    - `/login` / `/register`: Auth flow.

---

## 🛠️ Building and Running

### Prerequisites
- Docker & Docker Compose
- Node.js (for frontend development)
- PHP 8.5+ (locally or via Docker)

### Setup Steps
1. **Infrastructure:**
   ```bash
   docker-compose up -d
   ```
2. **Backend Setup:**
   ```bash
   cd backend
   composer install
   php bin/console doctrine:migrations:migrate
   php bin/console app:sample-data:create # Seed initial data
   ```
3. **Frontend Setup:**
   ```bash
   cd frontend
   npm install
   npm run dev
   ```

### Access Points
- **Frontend:** http://localhost:3000
- **API (via Nginx):** http://localhost:8000
- **Adminer:** http://localhost:8080
- **pgAdmin:** http://localhost:5050

---

## 🧠 AI Integration

The `AiClient` (`backend/src/Domain/Ai/AiClient.php`) is the central hub for AI requests.
- **Current Endpoint:** Configured to a local IP (placeholder).
- **Trait Inference:** `AiPlayerService` converts free-form text descriptions into structured numerical traits (0.0 to 1.0) and summaries using JSON schemas in prompts.
- **Logging:** All prompts and responses are stored with duration and metadata.

---

## 📏 Development Conventions

### PHP 8.5+ Coding Standards & Style Guide
These rules serve as the binding foundation for PHPStan (Level Max) and PHP_CodeSniffer. Any violation should be treated as technical debt.

#### 1. File & Class Structure
- **Strict Types:** Every PHP file must start with `declare(strict_types=1);`.
- **Order of Elements:** 
    1.  **Constants:** Grouped into logical blocks (separated by a blank line). Sorted alphabetically within each block.
    2.  **Properties:** Explicitly declared; **no Constructor Promotion**. Ordered by visibility: `private`, `protected`, `public`. Sorted alphabetically or by logical connection within the same visibility.
    3.  **Constructor.**
    4.  **Public Methods:** Primary use-case methods first.
    5.  **Getters/Setters:** (Setters only if absolutely necessary) at the end of the public section.
    6.  **Protected Methods.**
    7.  **Private Methods.**
- **Spacing:** Exactly one blank line between methods and between property/constant blocks. No blank lines at the beginning or end of the class body.

#### 2. Properties, Immutability & Types
- **Encapsulation:** All properties MUST be `private`.
- **Accessors:** Use `public` getters. Avoid generic `set*` methods; use semantic methods (e.g., `applyDamage()` instead of `setHealth()`).
- **Strict Typing:** 
    - Native type hints are mandatory for all properties, parameters, and return values.
    - If native types are insufficient (e.g., array of objects), PHPStan docblocks MUST be used (e.g., `/** @var array<int, Player> */`).
    - Use `void` for methods with no return value.
    - `readonly`: Use for all Value Objects, DTOs, and Result Objects.
- **Entities:** Strive for maximum immutability. State changes should ideally create new records/milestones rather than simply overwriting properties.

#### 3. Time Handling
- **Immutable Time:** Use only `DateTimeImmutable` for all time-related operations.
- **Time Injection:** The "current time" must never be fetched inside a Service or Entity (e.g., no `new DateTimeImmutable()`). 
- **Flow:** The Facade is responsible for obtaining the current time and passing it as an argument to Services and Entities.

#### 4. Data Structures & Return Values
- **No Nested Arrays:** Complex data structures must not be passed as arrays. Always use a **Value Object** or **DTO**.
- **Result Objects:** Methods returning more than one atomic type or a single VO must return a Result Object named `[MethodName]Result`.
- **DTOs:** Define all DTOs and Result Objects as `readonly class`.

#### 5. Naming & Logic (Business & Repository)
- **`find*`:** Returns `T|null`. Used for optional data.
- **`get*`:** Returns `T`. MUST throw a specific Domain Exception (e.g., `PlayerNotFoundException`) if the result does not exist.
- **Interfaces:** Named without `I` prefix or `Interface` suffix (e.g., `PlayerRepository`). Implementations may use a suffix like `DoctrinePlayerRepository`.
- **Booleans:** Methods returning bool should start with `is*`, `has*`, or `can*`.
- **Early Returns:** Favor "fail fast" and early returns over nested `if` blocks.

#### 6. Doctrine & DQL Guidelines
- **No Short Aliases:** In DQL/QueryBuilder, use the full entity name as the alias (e.g., `SELECT Player FROM App\Entity\Player Player`).
- **Atomic Parameters:** Pass only IDs (scalars) to repository and DQL methods. The repository is responsible for fetching references or entities if needed.
- **Type Safety:** Query results must be immediately validated or mapped to the expected type to satisfy PHPStan.

### Backend Architecture (Strict Pattern)
The project strictly follows the **Controller -> Facade -> Service** architecture:

1.  **Controller:** Handles Request/Response only. Dispatches to the Facade.
2.  **Facade:**
    *   Injects `EntityManagerInterface`, Repositories, and Services.
    *   **Infrastructure Boundary:** Only the Facade can access Doctrine, FileSystem, or external APIs.
    *   **Orchestration:** Fetches entities by ID, obtains current time, and passes data to Services.
    *   **Persistence:** Calls `$em->flush()` at the end of the operation.
3.  **Service:**
    *   **Pure Logic:** No infrastructure dependencies.
    *   **Data Input:** Receives only processed data/entities/time from the Facade.
    *   **Callbacks:** If a Service needs to perform an action requiring infrastructure (e.g., fetching data in a loop), the Facade must pass a closure/callback. The Service executes it without knowing the implementation.

### General Rules
- **Code Style:** Follow standard PSR-12 for PHP. Use TypeScript for Nuxt.
- **Database:** Use migrations for all schema changes (`php bin/console make:migration`).
- **AI Prompts:** Prompts are currently embedded in services (e.g., `AiPlayerService`). Future iterations may move these to templates.
- **Testing:** (TODO) Identify and implement testing patterns for AI-driven logic.
- **Commit Messages:** Clear, concise messages.

---

## 🗺️ Project Roadmap (Status)
- [x] Initial character trait generation (Text -> LLM -> JSON).
- [x] AI interaction logging and auditing.
- [x] Basic Admin UI for trait and user management.
- [ ] Real-time conversation engine (Chat mode).
- [ ] Tick-based simulation engine (2h game blocks).
- [ ] Relationship simulation (Real vs. Memory states).
- [ ] Webhook/Worker integration for asynchronous AI processing.
