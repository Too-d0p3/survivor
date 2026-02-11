# Backend Coding Standards

Binding rules for the PHP backend. Every violation is technical debt.

**Tooling:** PHPStan Level Max, PHP_CodeSniffer (PSR-12).

---

## 1. File Structure

Every PHP file starts with:

```php
<?php

declare(strict_types=1);
```

No closing `?>` tag.

One class/enum/interface per file. File name matches class name exactly.

---

## 2. Class Design

### 2.1 Final by Default

All classes are `final` unless explicitly designed for inheritance. Doctrine entities, DTOs, Value Objects, Services, Facades, Controllers — all `final`.

Exceptions (the only non-final classes):
- Abstract base classes (`AbstractApiController`)
- Doctrine repositories (extend `ServiceEntityRepository`)
- Exception classes (extend `RuntimeException` or domain base exception)

### 2.2 Readonly Classes

DTOs, Value Objects, and Result Objects are declared as `readonly class`. This enforces immutability at the language level — all properties become implicitly readonly.

```php
final readonly class GenerateTraitsInput
{
    public string $description;

    public function __construct(string $description)
    {
        $this->description = $description;
    }
}
```

### 2.3 Element Order Inside a Class

1. **Constants** — grouped logically, alphabetical within each group, groups separated by a blank line
2. **Properties** — `private` only, no constructor promotion in entities, ordered by logical connection
3. **Constructor**
4. **Public methods** — primary use-case methods first
5. **Getters / Setters** — setters only when unavoidable; prefer semantic methods (`applyDamage()` over `setHealth()`)
6. **Protected methods**
7. **Private methods**

One blank line between methods. No blank lines at the start or end of the class body.

### 2.4 No Constructor Promotion

Constructor promotion is **never used**. All properties are declared explicitly above the constructor and assigned in the constructor body. This keeps property declarations in one place, makes the class structure scannable, and allows attributes (Doctrine, Serializer, Validator) to live on the property — not buried inside a parameter list.

```php
// Wrong — constructor promotion
public function __construct(
    private readonly PlayerRepository $playerRepository,
) {
}

// Right — explicit declaration
private readonly PlayerRepository $playerRepository;

public function __construct(PlayerRepository $playerRepository)
{
    $this->playerRepository = $playerRepository;
}
```

### 2.5 No Property Hooks or Asymmetric Visibility

PHP 8.4+ property hooks (`get`/`set` on properties) and asymmetric visibility (`public private(set)`) are not used. Properties remain plain declarations with explicit getter/setter methods. This keeps the class structure conventional and predictable.

---

## 3. Type System

### 3.1 Native Types Everywhere

Every property, parameter, and return value has a native type hint. No exceptions.

Use `void` for methods that return nothing. Use `never` for methods that always throw.

### 3.2 PHPStan Docblocks

Add docblocks only when native types are insufficient — generics, array shapes, union types that PHP cannot express natively:

```php
/** @var ArrayCollection<int, PlayerTrait> */
private Collection $playerTraits;

/** @return array<string, float> */
public function getTraitScores(): array
```

Do not duplicate native type hints in docblocks.

### 3.3 Strict Null Handling

Prefer non-nullable types. When a value can genuinely be absent, use `?Type`. Never use `null` as a sentinel for "not yet initialized" — set proper defaults or require the value in the constructor.

### 3.4 No Nested Arrays as Data Structures

Arrays are only for homogeneous collections (`array<int, Player>`, `array<string, float>`). Heterogeneous data must be a Value Object or DTO.

```php
// Wrong
return ['player' => $player, 'score' => 42.5];

// Right
return new PlayerScoreResult($player, 42.5);
```

---

## 4. Enums

Use native PHP `enum` for all finite sets of values. Backed enums (`string` or `int`) for anything stored in the database or serialized.

```php
enum TraitType: string
{
    case Social = 'social';
    case Strategic = 'strategic';
    case Emotional = 'emotional';
    case Physical = 'physical';
}
```

No class constants for enumerable values. Existing constants with `ALLOWED_*` arrays must be migrated to enums.

---

## 5. Properties & Immutability

### 5.1 Encapsulation

All properties are `private`. No `public` or `protected` properties outside of `readonly class` DTOs/VOs (where constructor-promoted `public` properties are the standard pattern).

### 5.2 Accessors

Public getters for read access. No generic `set*()` methods — use semantic methods that express intent:

```php
// Wrong
$player->setHealth(80);
$player->setEliminated(true);

// Right
$player->applyDamage(20);
$player->eliminate();
```

Setters that return `$this` (fluent interface) are acceptable only during entity construction or for simple configuration objects. In that case, return `static`.

### 5.3 Entity Immutability

Entities favor immutability. State changes should be expressed through semantic methods. When it makes sense architecturally, prefer creating new records/milestones over overwriting properties.

---

## 6. Time Handling

- **Only `DateTimeImmutable`.** Never use mutable `DateTime` or `Types::DATETIME_MUTABLE`.
- **Never construct time in Services or Entities.** No `new DateTimeImmutable()` inside business logic.
- **Facade provides time.** The Facade obtains the current time (`new DateTimeImmutable()`) and passes it as a parameter to Services and Entities.

```php
// Facade
$now = new DateTimeImmutable();
$this->gameService->startRound($game, $now);

// Entity
public function start(DateTimeImmutable $startedAt): void
{
    $this->startedAt = $startedAt;
}
```

---

## 7. DTOs, Value Objects & Result Objects

### 7.1 DTOs (Data Transfer Objects)

For request input — carrying validated data from the outside world into the domain.

```php
final readonly class RegisterInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public string $password;

    public function __construct(string $email, string $password)
    {
        $this->email = $email;
        $this->password = $password;
    }
}
```

- Always `final readonly class`
- Properties declared explicitly above constructor with validation attributes
- `public` properties (serializer needs direct access)

### 7.2 Value Objects

For domain concepts with no identity — immutable, compared by value.

```php
final readonly class TraitScore
{
    public TraitType $type;
    public float $strength;

    public function __construct(TraitType $type, float $strength)
    {
        if ($strength < 0.0 || $strength > 1.0) {
            throw new InvalidArgumentException('Strength must be between 0.0 and 1.0');
        }

        $this->type = $type;
        $this->strength = $strength;
    }
}
```

- Always `final readonly class`
- Validate invariants in constructor

### 7.3 Result Objects

For methods returning complex data. Named `[MethodName]Result`.

```php
final readonly class GenerateTraitsResult
{
    public string $summary;

    /** @var array<int, TraitScore> */
    public array $scores;

    /**
     * @param array<int, TraitScore> $scores
     */
    public function __construct(string $summary, array $scores)
    {
        $this->summary = $summary;
        $this->scores = $scores;
    }
}
```

---

## 8. Architecture: Controller → Facade → Service

### 8.1 Controller

Handles HTTP only. Deserializes input, validates, dispatches to Facade, serializes output.

```php
#[Route('/api/players/{id}/traits', methods: ['POST'])]
public function generateTraits(
    int $id,
    Request $request,
    #[CurrentUser] ?User $user,
): JsonResponse {
    $dto = $this->getValidatedDto($request, GenerateTraitsInput::class);
    $result = $this->playerFacade->generateTraits($id, $dto);

    return $this->json($result, 200, [], ['groups' => 'player:read']);
}
```

- No business logic
- No direct Doctrine access
- No `$em->flush()`

### 8.2 Facade

The infrastructure boundary. The only layer that touches Doctrine, filesystem, external APIs, or system clock.

```php
public function generateTraits(int $playerId, GenerateTraitsInput $input): Player
{
    $now = new DateTimeImmutable();
    $player = $this->playerRepository->getPlayer($playerId);
    
    $result = $this->playerService->generateTraits($player, $input->description, $now);

    $this->entityManager->flush();

    return $player;
}
```

- Injects `EntityManagerInterface`, repositories, infrastructure services
- Fetches entities by ID
- Obtains current time
- Passes data/entities/time to Services
- Calls `$em->flush()` once at the end

### 8.3 Service

Pure business logic. Zero infrastructure dependencies.

```php
final class PlayerService
{
    public function calculateScore(Player $player, DateTimeImmutable $now): CalculateScoreResult
    {
        // Pure logic only — no Doctrine, no HTTP, no filesystem
    }
}
```

- No `EntityManagerInterface`
- No repository injections
- No HTTP clients
- Receives only processed data, entities, and time from Facade
- When infrastructure access is needed in a loop, Facade passes a `Closure`

---

## 9. Naming Conventions

### 9.1 Methods

| Pattern | Returns | Contract |
|---------|---------|----------|
| `find*()` | `T\|null` | Returns null when not found |
| `get*()` | `T` | Throws domain exception when not found |
| `is*()`, `has*()`, `can*()` | `bool` | Boolean query |
| `create*()` | `T` | Factory method |
| `apply*()`, `execute*()` | `void` or Result | State mutation / action |

### 9.2 No Abbreviations in Names

Variables, properties, parameters, methods, and classes use full descriptive names. No abbreviations.

```php
// Wrong
$em, $repo, $desc, $cfg, $msg, $btn, $idx, $cnt, $tmp
$playerRepo, $traitDesc

// Right
$entityManager, $repository, $description, $configuration, $message, $button, $index, $count, $temporary
$playerRepository, $traitDescription
```

Well-established acronyms that are more recognizable than their expansions are acceptable: `$id`, `$url`, `$html`, `$json`, `$dto`, `$api`, `$jwt`.

### 9.3 Interfaces

No `I` prefix, no `Interface` suffix. Implementations use a descriptive prefix:

```
PlayerRepository           ← interface
DoctrinePlayerRepository   ← implementation
```

### 9.4 Exceptions

`Cannot[Action]Because[Reason]Exception` — fully self-documenting:

```
CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException
CannotStartGameBecauseNotEnoughPlayersException
```

### 9.5 Domain Directory Structure

Each domain lives in `src/Domain/{DomainName}/` and contains its own:

```
src/Domain/Player/
├── Player.php                    # Entity
├── PlayerController.php          # Controller
├── PlayerFacade.php              # Facade
├── PlayerService.php             # Service
├── PlayerRepository.php          # Repository
├── PlayerSampleData.php          # Sample data seeder
├── Dto/
│   └── GenerateTraitsInput.php   # Input DTOs
├── Result/
│   └── GenerateTraitsResult.php  # Result objects
├── Enum/
│   └── PlayerStatus.php          # Domain enums
└── Exceptions/
    └── PlayerNotFoundException.php
```

---

## 10. Exceptions

### 10.1 Domain Exceptions

Extend `RuntimeException`. Accept context data in constructor. Provide typed getters for that data.

```php
final class PlayerNotFoundException extends RuntimeException
{
    private readonly int $playerId;

    public function __construct(int $playerId, ?Throwable $previous = null)
    {
        $this->playerId = $playerId;

        parent::__construct(
            sprintf('Player with ID %d not found', $playerId),
            0,
            $previous,
        );
    }

    public function getPlayerId(): int
    {
        return $this->playerId;
    }
}
```

### 10.2 Exception Hierarchy

- `RuntimeException` — base for all domain exceptions (unexpected state that cannot be recovered from within the domain)
- `InvalidArgumentException` — invalid input to Value Objects or domain methods (programming error, not user input)
- Never catch `\Exception` or `\Throwable` broadly — catch specific exception types

### 10.3 No Exception Swallowing

Never catch an exception and silently ignore it. Either handle it, rethrow it, or wrap it in a domain exception.

---

## 11. Doctrine & Database

### 11.1 Mapping

Use PHP 8 attributes exclusively. No annotations, XML, or YAML mapping.

```php
#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[ORM\Table(name: 'player')]
final class Player
```

### 11.2 DQL

Full entity name as alias — no short aliases:

```php
// Wrong
$qb->select('p')->from(Player::class, 'p');

// Right
$qb->select('Player')->from(Player::class, 'Player');
```

### 11.3 Repository Methods

- Accept only scalar IDs, never entity objects
- Return typed results — immediately validate/map query output
- Custom finder methods follow the `find*`/`get*` convention (section 9.1)

### 11.4 Migrations

All schema changes go through migrations (`make:migration`). Never modify the database manually. Migration files use `final class` and `declare(strict_types=1)`.

### 11.5 Column Types

- DateTime columns: always `Types::DATETIME_IMMUTABLE`
- Money/precision: `Types::DECIMAL` with explicit precision and scale
- Enums: `Types::STRING` with the enum's backed type, using `enumType` parameter

```php
#[ORM\Column(type: Types::STRING, enumType: TraitType::class)]
private TraitType $type;
```

---

## 12. Validation

### 12.1 Input Validation

Symfony Validator attributes on DTOs. Validated in the Controller layer before passing to Facade.

```php
final readonly class CreateGameInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 100)]
        public string $name,

        #[Assert\Range(min: 2, max: 20)]
        public int $playerCount,
    ) {
    }
}
```

### 12.2 Domain Validation

Value Objects validate their own invariants in the constructor. Entities validate state transitions in their semantic methods. This is separate from input validation — it guards domain integrity.

```php
public function eliminate(): void
{
    if ($this->isEliminated()) {
        throw new CannotEliminatePlayerBecauseAlreadyEliminatedException($this->id);
    }
    $this->eliminatedAt = ...;
}
```

### 12.3 No Validation in Services

Services trust their inputs. Validation happens at the boundary (Controller for user input, Value Object constructors for domain invariants, Facade for entity existence).

---

## 13. Control Flow

### 13.1 Early Returns

Favor "fail fast" and early returns over nested `if` blocks:

```php
// Wrong
public function process(Player $player): void
{
    if ($player->isActive()) {
        if ($player->hasTraits()) {
            // actual logic deeply nested
        }
    }
}

// Right
public function process(Player $player): void
{
    if (!$player->isActive()) {
        return;
    }

    if (!$player->hasTraits()) {
        return;
    }

    // actual logic at top level
}
```

### 13.2 No `else` After Return/Throw

```php
// Wrong
if ($condition) {
    return $a;
} else {
    return $b;
}

// Right
if ($condition) {
    return $a;
}

return $b;
```

### 13.3 Match Over Switch

Use `match` expressions instead of `switch` statements when mapping values:

```php
$label = match ($status) {
    PlayerStatus::Active => 'Playing',
    PlayerStatus::Eliminated => 'Out',
    PlayerStatus::Winner => 'Champion',
};
```

---

## 14. Collections

### 14.1 Doctrine Collections

Initialize in constructor. Type with `Collection` interface, instantiate with `ArrayCollection`:

```php
/** @var Collection<int, PlayerTrait> */
private Collection $playerTraits;

public function __construct()
{
    $this->playerTraits = new ArrayCollection();
}
```

### 14.2 Relationship Management

Bidirectional relationships must be managed on both sides:

```php
public function addPlayerTrait(PlayerTrait $trait): void
{
    if (!$this->playerTraits->contains($trait)) {
        $this->playerTraits->add($trait);
        $trait->setPlayer($this);
    }
}
```

### 14.3 Return Immutable Views

Getters for collections return arrays or readonly views, never the internal `Collection` object:

```php
/** @return array<int, PlayerTrait> */
public function getPlayerTraits(): array
{
    return $this->playerTraits->toArray();
}
```

---

## 15. Serialization

Use Symfony Serializer with groups for API responses:

```php
#[Groups(['player:read'])]
private string $name;
```

Controller specifies groups when returning JSON:

```php
return $this->json($entity, 200, [], ['groups' => 'player:read']);
```

Never expose internal entity structure directly — use serialization groups to control the API shape.

---

## 16. Testing

### 16.1 Test Location

Tests live in `backend/tests/` mirroring the `src/` structure:

```
tests/
├── Domain/
│   ├── Player/
│   │   ├── PlayerServiceTest.php
│   │   └── PlayerFacadeTest.php
│   └── Game/
│       └── GameServiceTest.php
└── Integration/
    └── ...
```

### 16.2 Unit Tests for Services

Services are pure logic — unit test them with real entities and no mocks:

```php
final class PlayerServiceTest extends TestCase
{
    public function testCalculateScore(): void
    {
        $service = new PlayerService();
        $player = new Player('Test Player');
        // ... setup and assertions
    }
}
```

### 16.3 Integration Tests for Facades

Facades need a database. Use Symfony's `KernelTestCase` with a test database:

```php
final class PlayerFacadeTest extends KernelTestCase
{
    public function testGenerateTraits(): void
    {
        self::bootKernel();
        $facade = self::getContainer()->get(PlayerFacade::class);
        // ...
    }
}
```

### 16.4 Test Naming

`test[MethodName][Scenario]` — describes what is being tested and under what conditions:

```
testCalculateScoreWithAllTraitsReturnsMaximum
testRegisterUserWithDuplicateEmailThrowsException
```

---

## 17. Dependency Injection

### 17.1 Constructor Injection Only

No property injection, no setter injection, no `#[Required]` attribute.

### 17.2 Autowiring

Rely on Symfony autowiring. Manual service definitions only when disambiguation is needed.

### 17.3 Interface Binding

When a service has an interface, bind the interface in the container. Inject the interface, never the implementation.

---

## 18. API Conventions

### 18.1 Routes

```php
#[Route('/api/{domain}/{action}', methods: ['POST'])]
```

- Prefix all API routes with `/api/`
- Use plural nouns for resource collections (`/api/players`)
- Use HTTP methods semantically: `GET` for reads, `POST` for creation/actions, `PUT` for full updates, `DELETE` for removal

### 18.2 Response Format

Success:
```json
{ "data": { ... } }
```

Validation errors (400):
```json
{ "errors": [ { "field": "email", "message": "This value should not be blank." } ] }
```

Domain errors (409, 404, etc.):
```json
{ "error": "Cannot register user because user with email `x@y.cz` already exists" }
```

### 18.3 Authentication

JWT via `LexikJWTAuthenticationBundle`. Current user injected with `#[CurrentUser]`:

```php
public function action(#[CurrentUser] ?User $user): JsonResponse
{
    if ($user === null) {
        return $this->json(['error' => 'Not authenticated'], 401);
    }
}
```

---

## 19. Code Hygiene

- No `@suppress`, `@phpstan-ignore`, or other static analysis suppressions without a comment explaining why
- No `TODO` in committed code without an associated issue/ticket reference
- No commented-out code — version control is the history
- No magic numbers — use named constants or enum cases
- No string concatenation for log messages or queries — use `sprintf()` or parameterized queries
