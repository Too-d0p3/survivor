# Relationship System (Phase 1) -- Architecture Blueprint

**Status:** Proposed
**Date:** 2026-02-27
**Author:** Architect Agent

---

## 1. Overview

When a game is created, AI generates initial relationships between all players. Each pair of players has two directed Relationship entities (A->B and B->A), each with trust/affinity/respect/threat scores. These are initialized by AI based on player personality traits and descriptions.

---

## 2. Domain Placement Decision

**Decision:** Relationship lives in its own domain: `src/Domain/Relationship/`.

**Rationale:**
- Relationship is its own aggregate -- it has identity, state, and invariants independent of Player.
- It will grow in future phases (memory_state, events, decay) and needs its own service/facade boundary.
- Placing it under Player would bloat the Player domain and create an awkward dependency back to Player.
- Placing it under Game would violate SRP -- Game manages game lifecycle, not inter-player dynamics.
- The Ai domain's new `InitializeRelationshipsOperation` stays in `Ai/Operation/` following the established pattern (all AI operations live in the Ai domain).

**Dependency direction:**
- `Relationship -> Player` (Relationship references Player entities)
- `Game -> Relationship` (GameFacade orchestrates relationship initialization during game creation)
- `Ai -> Relationship` (InitializeRelationshipsOperation returns a Result used by Relationship domain)
- No circular dependencies introduced.

---

## 3. Entity Design

### 3.1 Relationship Entity

**File:** `backend/src/Domain/Relationship/Relationship.php`

```
Relationship
  id: Uuid (v7, generated in constructor)
  source: Player (ManyToOne, JoinColumn nullable: false)
  target: Player (ManyToOne, JoinColumn nullable: false)
  trust: int (default 50)
  affinity: int (default 50)
  respect: int (default 50)
  threat: int (default 50)
  createdAt: DateTimeImmutable
  updatedAt: DateTimeImmutable
```

**Doctrine mapping:**
- `#[ORM\Entity(repositoryClass: RelationshipRepository::class)]`
- `#[ORM\Table(name: 'relationship')]`
- `#[ORM\UniqueConstraint(name: 'uniq_relationship_source_target', columns: ['source_id', 'target_id'])]`
- Score columns: `#[ORM\Column(type: Types::INTEGER)]` (no precision/scale -- plain integers 0-100)
- Time columns: `#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]`

**Constructor signature:**
```php
public function __construct(
    Player $source,
    Player $target,
    int $trust,
    int $affinity,
    int $respect,
    int $threat,
    DateTimeImmutable $createdAt,
)
```

**Constructor invariants:**
1. `$source` and `$target` must not be the same Player instance. Check via `$source->getId()->equals($target->getId())`. Throws `CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException`.
2. All score values clamped to [0, 100] via `self::clamp()`. No exception on out-of-range -- just clamp silently. Rationale: AI may produce edge values and clamping is the expected normalization behavior.
3. `$this->updatedAt = $createdAt` (initial state).

**Constants:**
```php
private const int MIN_SCORE = 0;
private const int MAX_SCORE = 100;
private const int DEFAULT_SCORE = 50;
```

**Mutation methods (semantic, not setters):**
```php
public function adjustTrust(int $delta, DateTimeImmutable $now): void
public function adjustAffinity(int $delta, DateTimeImmutable $now): void
public function adjustRespect(int $delta, DateTimeImmutable $now): void
public function adjustThreat(int $delta, DateTimeImmutable $now): void
```

Each adjust method:
1. Adds `$delta` to current value
2. Clamps result to [0, 100]
3. Sets `$this->updatedAt = $now`

**Private helper:**
```php
private static function clamp(int $value): int
{
    return max(self::MIN_SCORE, min(self::MAX_SCORE, $value));
}
```

**Getters:**
```php
public function getId(): Uuid
public function getSource(): Player
public function getTarget(): Player
public function getTrust(): int
public function getAffinity(): int
public function getRespect(): int
public function getThreat(): int
public function getCreatedAt(): DateTimeImmutable
public function getUpdatedAt(): DateTimeImmutable
```

**Rationale for int 0-100 vs float 0.0-1.0:**
- PlayerTrait uses `DECIMAL(3,2)` (0.00-1.00) because trait strengths are continuous, fine-grained values that AI generates.
- Relationship scores use int 0-100 because they are discrete, human-readable values that change by integer deltas during game events. Integer math avoids floating-point comparison issues.

### 3.2 No Bidirectional Collection on Player

The Player entity does NOT get a `OneToMany` collection for relationships. Rationale:
- Relationship queries should go through RelationshipRepository, not lazy-loaded collections.
- Player is already a loaded entity (has playerTraits collection). Adding two more collections (sourceRelationships, targetRelationships) would cause N+1 issues and tight coupling.
- This follows the principle of keeping aggregates lean.

---

## 4. Repository

**File:** `backend/src/Domain/Relationship/RelationshipRepository.php`

```php
/**
 * @extends ServiceEntityRepository<Relationship>
 */
class RelationshipRepository extends ServiceEntityRepository
```

**Methods:**

```php
public function __construct(ManagerRegistry $registry)

/**
 * @throws RelationshipNotFoundException
 */
public function getRelationship(Uuid $relationshipId): Relationship

/**
 * @throws RelationshipNotFoundException
 */
public function getBySourceAndTarget(Uuid $sourceId, Uuid $targetId): Relationship

/**
 * @return array<int, Relationship>
 */
public function findByGame(Uuid $gameId): array
```

**`findByGame` implementation notes:**
- Uses DQL joining through `Relationship.source` -> `Player.game` where `Player.game = :gameId`.
- Full entity alias per coding standard: `Relationship`, `Player`.
- Returns all relationships for all players in a given game.

**`getBySourceAndTarget` implementation notes:**
- Uses `findOneBy(['source' => $sourceId, 'target' => $targetId])` or a QueryBuilder.
- Throws `RelationshipNotFoundException` if not found.

---

## 5. Exceptions

**Directory:** `backend/src/Domain/Relationship/Exceptions/`

### 5.1 RelationshipNotFoundException

**File:** `RelationshipNotFoundException.php`

Follows the exact pattern of `GameNotFoundException` and `PlayerNotFoundException`:
```php
final class RelationshipNotFoundException extends RuntimeException
{
    private readonly Uuid $relationshipId;

    public function __construct(Uuid $relationshipId, ?Throwable $previous = null)
    {
        $this->relationshipId = $relationshipId;
        parent::__construct(
            sprintf('Relationship with id `%s` not found', $relationshipId->toString()),
            0,
            $previous,
        );
    }

    public function getRelationshipId(): Uuid
    {
        return $this->relationshipId;
    }
}
```

### 5.2 CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException

**File:** `CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException.php`

Follows the `Cannot[Action]Because[Reason]Exception` pattern:
```php
final class CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException extends RuntimeException
{
    private readonly Player $player;

    public function __construct(Player $player, ?Throwable $previous = null)
    {
        $this->player = $player;
        parent::__construct(
            sprintf(
                'Cannot create relationship because source and target are the same player `%s`',
                $player->getId()->toString(),
            ),
            0,
            $previous,
        );
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }
}
```

---

## 6. AI Operation: InitializeRelationshipsOperation

**File:** `backend/src/Domain/Ai/Operation/InitializeRelationshipsOperation.php`

```php
/**
 * @implements AiOperation<InitializeRelationshipsResult>
 */
final readonly class InitializeRelationshipsOperation implements AiOperation
```

### 6.1 Input Data

The Operation receives player data as a structured VO array, not raw entities (Operations are in the Ai domain and should not depend on complex entity graphs):

```php
/** @var array<int, RelationshipPlayerData> */
private array $players;

/**
 * @param array<int, RelationshipPlayerData> $players
 */
public function __construct(array $players)
```

### 6.2 RelationshipPlayerData VO

**File:** `backend/src/Domain/Ai/Dto/RelationshipPlayerData.php`

```php
final readonly class RelationshipPlayerData
{
    public string $name;
    public string $description;
    /** @var array<string, string> */
    public array $traitStrengths;

    /**
     * @param array<string, string> $traitStrengths
     */
    public function __construct(string $name, string $description, array $traitStrengths)
    {
        $this->name = $name;
        $this->description = $description;
        $this->traitStrengths = $traitStrengths;
    }
}
```

### 6.3 Operation Methods

```php
public function getActionName(): string
{
    return 'initializeRelationships';
}

public function getTemplateName(): string
{
    return 'initialize_relationships';
}

public function getTemplateVariables(): array
{
    return [];
}

public function getMessages(): array
{
    return [AiMessage::user($this->formatMessage())];
}

public function getTemperature(): ?float
{
    return null;  // Use default
}
```

### 6.4 Response Schema

The AI returns a flat array of relationship entries. Each entry identifies a directed pair by 1-based player indexes:

```json
{
  "relationships": [
    {
      "source_index": 1,
      "target_index": 2,
      "trust": 65,
      "affinity": 70,
      "respect": 55,
      "threat": 20
    }
  ]
}
```

For N players, the AI must return exactly N*(N-1) relationship entries (every ordered pair).

**Schema definition:**
```php
public function getResponseSchema(): AiResponseSchema
{
    return new AiResponseSchema(
        'object',
        [
            'relationships' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'source_index' => [
                            'type' => 'integer',
                            'description' => '1-based index of the source player',
                        ],
                        'target_index' => [
                            'type' => 'integer',
                            'description' => '1-based index of the target player',
                        ],
                        'trust' => [
                            'type' => 'integer',
                            'description' => 'Trust level 0-100',
                        ],
                        'affinity' => [
                            'type' => 'integer',
                            'description' => 'Affinity level 0-100',
                        ],
                        'respect' => [
                            'type' => 'integer',
                            'description' => 'Respect level 0-100',
                        ],
                        'threat' => [
                            'type' => 'integer',
                            'description' => 'Perceived threat level 0-100',
                        ],
                    ],
                    'required' => ['source_index', 'target_index', 'trust', 'affinity', 'respect', 'threat'],
                ],
            ],
        ],
        ['relationships'],
    );
}
```

### 6.5 Parse Method

```php
/**
 * @return InitializeRelationshipsResult
 */
public function parse(string $content): mixed
```

**Validation in parse():**
1. Valid JSON
2. Has `relationships` key, is array
3. Exactly `N*(N-1)` entries where N = `count($this->players)`
4. Each entry has `source_index`, `target_index` (both int, 1-based, in range [1, N])
5. `source_index !== target_index` for each entry
6. No duplicate `(source_index, target_index)` pairs
7. Each score field (`trust`, `affinity`, `respect`, `threat`) is int, in [0, 100]
8. All expected pairs present (every ordered combination covered)

All validation failures throw `AiResponseParsingFailedException` with descriptive messages (matching existing pattern in `GenerateBatchPlayerSummariesOperation`).

### 6.6 InitializeRelationshipsResult

**File:** `backend/src/Domain/Ai/Result/InitializeRelationshipsResult.php`

```php
final readonly class InitializeRelationshipsResult
{
    /** @var array<int, RelationshipScores> */
    private array $relationships;

    /**
     * @param array<int, RelationshipScores> $relationships
     */
    public function __construct(array $relationships)
    {
        $this->relationships = $relationships;
    }

    /**
     * @return array<int, RelationshipScores>
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }
}
```

### 6.7 RelationshipScores VO

**File:** `backend/src/Domain/Ai/Result/RelationshipScores.php`

```php
final readonly class RelationshipScores
{
    public int $sourceIndex;
    public int $targetIndex;
    public int $trust;
    public int $affinity;
    public int $respect;
    public int $threat;

    public function __construct(
        int $sourceIndex,
        int $targetIndex,
        int $trust,
        int $affinity,
        int $respect,
        int $threat,
    ) {
        $this->sourceIndex = $sourceIndex;
        $this->targetIndex = $targetIndex;
        $this->trust = $trust;
        $this->affinity = $affinity;
        $this->respect = $respect;
        $this->threat = $threat;
    }
}
```

**Note on index convention:** Uses 1-based player indexing (matching `GenerateBatchPlayerSummariesOperation`). The conversion from 1-based AI indexes to 0-based PHP array indexes happens in `RelationshipService.initializeRelationships()`.

### 6.8 formatMessage() -- User Message Construction

```php
private function formatMessage(): string
```

Builds a text message listing all players with their personality data. Format:

```
Hrac 1: Alex
Popis: Charismatic leader with hidden agenda.
Vlastnosti:
  leadership: 0.85
  empathy: 0.45
  ...

Hrac 2: Bara
...
```

Uses `$this->players` (the `RelationshipPlayerData[]` array). Players are separated by double newlines. This follows the pattern from `GenerateBatchPlayerSummariesOperation::formatMessage()`.

### 6.9 Prompt Template

**File:** `backend/src/Domain/Ai/Prompt/templates/initialize_relationships.md`

Czech language (consistent with existing templates). Content:

```markdown
Jsi system pro inicializaci vztahu mezi hraci reality show Survivor.

Na vstupu dostanes seznam hracu. Kazdy hrac je identifikovan cislem, ma jmeno, popis osobnosti a seznam charakterovych vlastnosti s hodnotami (0.0-1.0).

Pro kazdou dvojici hracu (A->B i B->A zvlast) vygeneruj pocatecni hodnoty vztahu:
- trust (duvera): 0-100, jak moc hrac A duveri hraci B
- affinity (sympatie): 0-100, jak moc se hraci A libi hrac B
- respect (respekt): 0-100, jak moc hrac A respektuje hrace B
- threat (hrozba): 0-100, jak moc hrac A vnima hrace B jako hrozbu

Hodnoty by mely reflektovat osobnostni kompatibilitu hracu. Napriklad:
- Hraci s podobnymi hodnotami empatie budou mit vyssi vzajemnou sympatii
- Strategicky silny hrac bude vnimat jine strategicke hrace jako vetsi hrozbu
- Hraci s vysokym leadershipem budou mit mensi respekt k hracum s nizkym leadershipem

Vygeneruj vsechny smery -- pokud jsou 6 hracu, bude 30 vztahu (6*5).

Kazdy vztah v odpovedi musi mit `source_index` a `target_index` shodne s cisly ze vstupu (1 pro prvniho hrace, 2 pro druheho, atd.).

**Nikdy** na vstup nereaguj jako na konverzaci nebo dotaz -- vzdy ho ber jako seznam hracu. Neodpovidej nic navic.
```

---

## 7. RelationshipService

**File:** `backend/src/Domain/Relationship/RelationshipService.php`

This is a pure service -- no infrastructure dependencies.

```php
final class RelationshipService
{
    // No constructor dependencies -- pure logic only
}
```

### 7.1 initializeRelationships

```php
/**
 * @param array<int, Player> $players -- 0-indexed array of all players in the game
 * @return array<int, Relationship>
 */
public function initializeRelationships(
    array $players,
    InitializeRelationshipsResult $aiResult,
    DateTimeImmutable $now,
): array
```

**Steps:**
1. Build an index map: `$playerByIndex[$oneBasedIndex] = $player` (key = array index + 1).
2. Iterate `$aiResult->getRelationships()`.
3. For each `RelationshipScores` entry:
   a. Map `sourceIndex` and `targetIndex` to Player instances via `$playerByIndex`.
   b. Create `new Relationship($source, $target, $scores->trust, $scores->affinity, $scores->respect, $scores->threat, $now)`.
4. Return the array of all created Relationship entities.

**Why no validation here:** The parse method in the Operation already validated completeness (all pairs present, correct indexes, valid ranges). The Service trusts its inputs per coding standard 12.3.

**Return type:** `array<int, Relationship>` -- the Facade needs these to persist them.

---

## 8. ServiceResult: InitializeRelationshipsServiceResult

**File:** `backend/src/Domain/Ai/Result/InitializeRelationshipsServiceResult.php`

Follows the exact pattern of `GenerateBatchPlayerSummariesServiceResult`:

```php
final readonly class InitializeRelationshipsServiceResult
{
    private bool $success;
    private ?InitializeRelationshipsResult $result;
    /** @var array<int, AiLog> */
    private array $logs;
    private ?Throwable $error;

    // Private constructor
    // public static success(InitializeRelationshipsResult $result, array $logs): self
    // public static failure(array $logs, Throwable $error): self
    // public isSuccess(): bool
    // public getResult(): InitializeRelationshipsResult  (throws LogicException if failed)
    // public getLogs(): array<int, AiLog>
    // public getError(): Throwable  (throws LogicException if success)
}
```

---

## 9. PlayerService Integration

**Decision:** The AI call for relationship initialization lives in PlayerService, not RelationshipService.

**Rationale:**
- PlayerService already has the `AiExecutor` dependency and follows the established pattern of composing AiOperations and returning ServiceResults.
- RelationshipService is pure logic (no AiExecutor), consistent with its domain responsibility.
- This matches the existing pattern where PlayerService calls `GenerateBatchPlayerSummariesOperation` even though summaries end up on Player entities.

**New method in PlayerService:**

```php
/**
 * @param array<int, RelationshipPlayerData> $players
 */
public function initializeRelationships(
    array $players,
    DateTimeImmutable $now,
): InitializeRelationshipsServiceResult
{
    $operation = new InitializeRelationshipsOperation($players);
    $callResult = $this->executor->execute($operation, $now);

    if (!$callResult->isSuccess()) {
        return InitializeRelationshipsServiceResult::failure(
            [$callResult->getLog()],
            $callResult->getError(),
        );
    }

    return InitializeRelationshipsServiceResult::success(
        $callResult->getResult(),
        [$callResult->getLog()],
    );
}
```

---

## 10. GameFacade Integration

The relationship initialization is added to the existing `createGame()` method in `GameFacade`.

### 10.1 New Dependencies

GameFacade needs two new injected dependencies:
- `RelationshipService $relationshipService`
- No RelationshipRepository needed yet (we only persist, never read during creation)

### 10.2 Revised createGame() Orchestration Plan

```
1.  $now = new DateTimeImmutable()
2.  Fetch all trait definitions from TraitDefRepository
3.  Generate random AI trait strengths (5 players)
4.  Call PlayerService::generateBatchPlayerTraitsSummaryDescriptions()
5.  Persist AI logs from step 4
6.  Flush (to persist logs even if AI failed)
7.  If step 4 failed, throw the error
8.  Call GameService::createGame() with all data -> CreateGameResult
9.  Persist game, players, and player traits
10. Build RelationshipPlayerData[] from all players in the game
11. Call PlayerService::initializeRelationships(playerData, $now)
12. Persist AI logs from step 11
13. If step 11 failed, flush logs and throw the error
14. Call RelationshipService::initializeRelationships(players, aiResult, $now)
15. Persist all Relationship entities
16. Final flush
17. Return CreateGameResult
```

### 10.3 Building RelationshipPlayerData

```php
$players = $game->getPlayers();
$playerDataList = [];

foreach ($players as $player) {
    $traitStrengths = [];
    foreach ($player->getPlayerTraits() as $playerTrait) {
        $traitStrengths[$playerTrait->getTraitDef()->getKey()] = $playerTrait->getStrength();
    }

    $playerDataList[] = new RelationshipPlayerData(
        $player->getName(),
        $player->getDescription() ?? '',
        $traitStrengths,
    );
}
```

### 10.4 Updated Method Signature

```php
public function createGame(
    User $owner,
    string $humanPlayerName,
    string $humanPlayerDescription,
    array $humanTraitStrengths,
): CreateGameResult
```

No change to the method signature. The method now does more internally but the contract is unchanged. The response still returns `CreateGameResult` -- relationships are persisted as a side effect and are not part of the game creation response (they can be queried separately later).

### 10.5 Flush Strategy

**Two flushes maximum:**
1. First flush: After persisting summary AI logs (existing behavior, step 5+6). This ensures AI logs are saved even if subsequent steps fail.
2. Second flush: At the end (step 16). Persists game + players + traits + relationship AI logs + relationships all together.

**Rationale for not separating relationship log flush:** If the relationship AI call fails, we need the log persisted. The simplest approach is to flush before throwing:
```php
// Step 12-13
foreach ($relationshipServiceResult->getLogs() as $log) {
    $this->entityManager->persist($log);
}

if (!$relationshipServiceResult->isSuccess()) {
    $this->entityManager->flush();
    throw $relationshipServiceResult->getError();
}
```

---

## 11. What Does NOT Change (Phase 1)

1. **No new API endpoint.** Relationships are initialized as part of game creation. A dedicated `GET /api/games/{gameId}/relationships` endpoint will come in Phase 2.
2. **No GameController changes.** The response shape of `POST /api/game/create` does not change. Relationships are not included in the create-game response.
3. **No Player entity changes.** No bidirectional collections added to Player.
4. **CreateGameResult remains unchanged.** It still wraps just `Game`.

---

## 12. Domain Invariants Summary

| # | Invariant | Enforced By |
|---|-----------|------------|
| 1 | Relationship source and target must be different players | Entity constructor |
| 2 | All scores are in [0, 100] | Entity constructor (clamp) + adjust methods (clamp) |
| 3 | (source_id, target_id) is unique per pair | DB UniqueConstraint |
| 4 | For N players, exactly N*(N-1) relationships are created | AI Operation parse validation |
| 5 | createdAt and updatedAt are always set | Entity constructor (updatedAt = createdAt) |
| 6 | updatedAt is updated on every score change | adjust* methods |

---

## 13. Error Mapping

| Exception | HTTP Status | When |
|-----------|-------------|------|
| `RelationshipNotFoundException` | 404 | Relationship entity not found (future Phase 2 endpoint) |
| `CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException` | 422 | Self-relationship attempted (should never happen from AI, defensive) |
| `AiResponseParsingFailedException` | 502 | AI returned malformed relationship data |
| `AiRequestFailedException` | 502 | AI API call failed |

---

## 14. File Inventory

### New Files

| File | Type |
|------|------|
| `src/Domain/Relationship/Relationship.php` | Entity |
| `src/Domain/Relationship/RelationshipRepository.php` | Repository |
| `src/Domain/Relationship/RelationshipService.php` | Service |
| `src/Domain/Relationship/Exceptions/RelationshipNotFoundException.php` | Exception |
| `src/Domain/Relationship/Exceptions/CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException.php` | Exception |
| `src/Domain/Ai/Operation/InitializeRelationshipsOperation.php` | AI Operation |
| `src/Domain/Ai/Dto/RelationshipPlayerData.php` | DTO/VO |
| `src/Domain/Ai/Result/InitializeRelationshipsResult.php` | Result VO |
| `src/Domain/Ai/Result/RelationshipScores.php` | Result VO |
| `src/Domain/Ai/Result/InitializeRelationshipsServiceResult.php` | Service Result VO |
| `src/Domain/Ai/Prompt/templates/initialize_relationships.md` | Prompt Template |

### Modified Files

| File | Change |
|------|--------|
| `src/Domain/Game/GameFacade.php` | Add RelationshipService dependency, relationship initialization steps in createGame() |
| `src/Domain/Player/PlayerService.php` | Add initializeRelationships() method |

### Migration

One new migration for the `relationship` table with:
- `id` UUID primary key
- `source_id` UUID FK to player
- `target_id` UUID FK to player
- `trust` INTEGER NOT NULL DEFAULT 50
- `affinity` INTEGER NOT NULL DEFAULT 50
- `respect` INTEGER NOT NULL DEFAULT 50
- `threat` INTEGER NOT NULL DEFAULT 50
- `created_at` TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL (DATETIME_IMMUTABLE)
- `updated_at` TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL (DATETIME_IMMUTABLE)
- UNIQUE(source_id, target_id)
- INDEX on source_id
- INDEX on target_id

---

## 15. Test Plan

### 15.1 Unit Tests

| Test Class | Location | Covers |
|------------|----------|--------|
| `RelationshipTest` | `tests/Unit/Domain/Relationship/RelationshipTest.php` | Entity constructor, clamp behavior, adjust methods, self-relationship exception |
| `RelationshipServiceTest` | `tests/Unit/Domain/Relationship/RelationshipServiceTest.php` | initializeRelationships() mapping from AI result to entities |
| `InitializeRelationshipsOperationTest` | `tests/Unit/Domain/Ai/Operation/InitializeRelationshipsOperationTest.php` | parse() validation, formatMessage(), schema, all error paths |
| `InitializeRelationshipsResultTest` | `tests/Unit/Domain/Ai/Result/InitializeRelationshipsResultTest.php` | Constructor, getter |
| `RelationshipScoresTest` | `tests/Unit/Domain/Ai/Result/RelationshipScoresTest.php` | Constructor, properties |
| `InitializeRelationshipsServiceResultTest` | `tests/Unit/Domain/Ai/Result/InitializeRelationshipsServiceResultTest.php` | success/failure factory, getters, LogicException guards |
| `RelationshipPlayerDataTest` | `tests/Unit/Domain/Ai/Dto/RelationshipPlayerDataTest.php` | Constructor, properties |
| `PlayerServiceTest` (update) | `tests/Unit/Domain/Player/PlayerServiceTest.php` | New initializeRelationships() method |
| `GameServiceTest` (no change) | -- | No changes needed (GameService not modified) |

### 15.2 Integration Tests

| Test Class | Location | Covers |
|------------|----------|--------|
| `GameFacadeTest` (update) | `tests/Integration/Domain/Game/GameFacadeTest.php` | createGame now persists relationships, AI logs for relationship init |

**Key test cases for GameFacadeTest:**
- `testCreateGamePersistsRelationshipsForAllPlayerPairs` -- assert 30 Relationship rows for 6 players
- `testCreateGameRelationshipsHaveAiGeneratedScores` -- verify scores are not all default 50
- `testCreateGamePersistsRelationshipAiLog` -- verify AI log for 'initializeRelationships' action

### 15.3 Functional Tests

| Test Class | Location | Covers |
|------------|----------|--------|
| `GameControllerTest` (update) | `tests/Functional/Domain/Game/GameControllerTest.php` | Existing tests still pass (response shape unchanged) |

**Mock updates:** The mock GeminiClient in test helpers must handle TWO AI calls:
1. First call: batch summaries (existing)
2. Second call: relationship initialization (new)

The mock must distinguish between calls. Options:
- Track call count and return different responses.
- Inspect the `AiRequest` action name.

Recommended approach: use a stateful mock that checks the request content or returns appropriate data for both calls. The simplest approach is a call counter:

```php
new class implements GeminiClient {
    private int $callCount = 0;

    public function request(AiRequest $aiRequest): AiResponse
    {
        $this->callCount++;

        if ($this->callCount === 1) {
            // Return batch summaries response
            ...
        }

        // Return relationships response
        ...
    }
};
```

### 15.4 Entity Unit Test Cases (RelationshipTest)

```
testConstructorSetsAllPropertiesCorrectly
testConstructorClampsValuesAbove100
testConstructorClampsValuesBelow0
testConstructorThrowsExceptionWhenSourceEqualsTarget
testAdjustTrustIncreasesValue
testAdjustTrustDecreasesBelowZeroClampsToZero
testAdjustTrustIncreasesAbove100ClampsTo100
testAdjustTrustUpdatesUpdatedAt
testAdjustAffinityIncreasesValue
testAdjustRespectIncreasesValue
testAdjustThreatIncreasesValue
testGettersReturnCorrectValues
```

### 15.5 RelationshipService Test Cases

```
testInitializeRelationshipsCreatesCorrectNumberOfRelationships
testInitializeRelationshipsMapsSourcesToCorrectPlayers
testInitializeRelationshipsMapsParsedScores
testInitializeRelationshipsWithTwoPlayersCreatesTwoRelationships
```

### 15.6 InitializeRelationshipsOperation Test Cases

```
testGetActionNameReturnsCorrectName
testGetTemplateNameReturnsCorrectName
testGetResponseSchemaHasRelationshipsKey
testGetTemperatureReturnsNull
testGetMessagesReturnsSingleUserMessage
testFormatMessageIncludesAllPlayerData
testParseValidResponseReturnsResult
testParseInvalidJsonThrowsException
testParseMissingRelationshipsKeyThrowsException
testParseWrongCountThrowsException
testParseDuplicatePairThrowsException
testParseSelfRelationshipThrowsException
testParseOutOfRangeIndexThrowsException
testParseOutOfRangeScoreThrowsException
testParseMissingScoreFieldThrowsException
testParseNonIntegerScoreThrowsException
```

---

## 16. Issues Found in Original Specification

### 16.1 ISSUE: "RelationshipService in Relationship or Game domain?"

**Resolution:** Relationship domain. It owns its own aggregate and service. GameFacade orchestrates across domains -- that is the Facade's job.

### 16.2 ISSUE: Constructor source !== target check uses identity comparison

**Problem:** The original spec said `source !== target`. PHP object identity (`!==`) would fail if the same Player were loaded twice by Doctrine (different PHP objects, same DB row). This is unlikely in the current flow but is a latent bug.

**Resolution:** Use `$source->getId()->equals($target->getId())`. Uuid has a proper `equals()` method. This is correct regardless of PHP object identity.

### 16.3 ISSUE: Values "clamped to 0-100" but score type is "int 0-100, default 50"

**Problem:** The original spec mentions clamping but does not specify whether clamping is silent or throws. In a domain where AI generates these values, throwing would cause failures for edge cases like AI returning 101.

**Resolution:** Silent clamping. Documented above as explicit design decision.

### 16.4 ISSUE: Service named "initializeRelationships" receives an AI result

**Problem:** The original spec has `RelationshipService.initializeRelationships()` receiving `InitializeRelationshipsResult` directly. This couples the Relationship domain to the AI domain's result types.

**Acceptable trade-off:** The coupling is one-directional (Relationship -> Ai Result VO). The alternative -- re-mapping into a Relationship-domain VO -- would create a redundant data structure for no meaningful decoupling benefit. The AI result VO is a simple data carrier with no behavior. This is acceptable.

### 16.5 ISSUE: AI operation uses 1-based indexing

**Verified:** This is consistent with `GenerateBatchPlayerSummariesOperation`. The 1-based convention is established project-wide for AI interaction. No change needed.

### 16.6 ISSUE: Missing consideration for flush strategy in GameFacade

**Problem:** The original spec did not address what happens if the relationship AI call fails after the game and players are already created in memory (but not yet flushed).

**Resolution:** The revised orchestration plan (Section 10.2) addresses this. Game + players + traits are persisted in the same final flush as relationships. If the relationship AI call fails, its logs are flushed and the error is thrown -- the game is NOT created. This is correct: a game without relationships is incomplete and should not be persisted.

**Exception:** Summary AI logs from step 4 are flushed early (existing behavior). This means if the relationship AI call fails, the summary AI log is persisted but the game is not. This is acceptable -- AI logs are diagnostic records, not domain state.

### 16.7 ISSUE: GameFacade flushes entities in current implementation after persist

**Observation:** The current `GameFacade.createGame()` has two flushes -- one after AI logs and one at the end. The revised plan maintains this pattern. However, adding a third potential flush (for relationship AI log on failure) means up to 3 flushes in the failure path. This is acceptable for correctness.

### 16.8 ISSUE: CreateGameInput DTO uses constructor promotion

**Observation:** `CreateGameInput` uses constructor promotion (`public function __construct(... public string $playerName = '' ...)`). This violates CODING_STANDARDS.md section 2.4. However, this is a pre-existing issue and outside the scope of this feature.

---

## 17. ADR: Relationship Domain Boundary

**Status:** Proposed

**Context:** The Relationship System introduces a new entity that connects two Player entities. The question is whether Relationship belongs in the Player domain, the Game domain, or its own domain.

**Decision:** Relationship gets its own domain at `src/Domain/Relationship/`. The AI Operation for initialization lives in the Ai domain (following the established pattern). The AI call orchestration for relationship initialization lives in PlayerService (which already has the AiExecutor dependency).

**Consequences:**
- (+) Clean separation of concerns: Relationship domain owns its entity, repository, service, and exceptions
- (+) No bloating of Player or Game domains
- (+) Future phases (memory_state, relationship events, decay) have a clear home
- (+) Dependency direction is clean: Relationship -> Player (entity reference), Game -> Relationship (orchestration)
- (-) One more domain directory to maintain
- (-) Cross-domain orchestration in GameFacade grows in complexity

**Alternatives Considered:**
1. Place in Player domain -- rejected because Relationship is between two players, not owned by one
2. Place in Game domain -- rejected because Game manages lifecycle/state, not inter-player dynamics
3. Inline everything in GameFacade without a service -- rejected because it violates the Controller->Facade->Service pattern (Facade should not contain business logic for entity creation)
