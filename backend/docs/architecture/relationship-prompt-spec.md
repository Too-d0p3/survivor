# Prompt Specification: InitializeRelationshipsOperation

**Version:** 1.0.0
**Date:** 2026-02-27
**Author:** Prompt Architect

---

## 1. Prompt Spec

### PromptName

`initialize_relationships`

### Version

`1.0.0`

### Purpose

Generate the initial perceived relationship values for all directed player pairs in a newly created Survivor game, based solely on each player's name, personality description, and trait strengths — before any in-game interaction has occurred.

### Information Tier

**Omniscient context** — this is a system-level initialization call, not a player decision prompt. The model receives full information about all players simultaneously and produces initial values for all pairs in a single call. No player's perspective is privileged over another's.

This is correct because: no game events have yet occurred, every player's impression of every other player is being formed for the first time, and there is no "ground truth" relationship state to protect yet.

### Model Requirements

- Structured output support: `responseMimeType: "application/json"` + `responseSchema` in `generationConfig`
- Minimum context window: 8 000 tokens (sufficient for 6 players × ~10 traits)
- No tool use, no code execution, no retrieval

### Temperature

`0.9` — higher than the default to produce varied first impressions. With a low temperature the model would produce near-identical neutral values for all pairs; the simulation needs meaningful initial asymmetry so relationships have somewhere to evolve.

---

## 2. Message Structure

### System message (loaded from template `initialize_relationships.md`)

See section 4 for the full Czech template text.

### User message

Constructed in PHP by `InitializeRelationshipsOperation::formatMessage()`. Passed as a single `AiMessage::user(...)`.

Format (one block per player, separated by blank lines):

```
Hráč 1: Jana
Popis: Ambiciózní sportovkyně, která si vždy prosadí svou.
Vlastnosti:
- loyal: 0.72
- strategic: 0.88
- manipulative: 0.31
...

Hráč 2: Alex
Popis: Tichý pozorovatel s analytickým myšlením.
Vlastnosti:
- loyal: 0.55
- strategic: 0.91
- manipulative: 0.44
...
```

The 1-based player index in the user message is the key that ties output `source_index`/`target_index` values back to their input players.

---

## 3. Inputs (PHP DTO)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

// Input type used internally by InitializeRelationshipsOperation constructor.
// Not a standalone DTO class — the operation accepts a typed array of PlayerRelationshipInput.

final readonly class PlayerRelationshipInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        /** @var array<string, string> $traitStrengths key => formatted float string "0.72" */
        public readonly array $traitStrengths,
    ) {}
}
```

The operation constructor signature:

```php
/**
 * @param array<int, PlayerRelationshipInput> $players  0-based array, 2–10 elements
 */
public function __construct(array $players)
```

**Validation rules in constructor:**

- Minimum 2 players, maximum 10 players (the simulation uses 6, but the operation must not hard-code this)
- Each `name` must be a non-empty string
- Each `description` must be a non-empty string (do not pass empty description; if description is not yet generated, the caller must generate it first)
- Each `traitStrengths` array must be non-empty
- Each trait strength value must be numeric and in range `[0.0, 1.0]` after casting; values are formatted to 2 decimal places before embedding in the user message

---

## 4. Prompt Template

File: `backend/src/Domain/Ai/Prompt/templates/initialize_relationships.md`

```markdown
Jsi systém pro generování počátečních vztahů mezi hráči reality show Survivor.

Na vstupu dostaneš seznam hráčů. Každý hráč je identifikován číslem, jménem, popisem osobnosti a sadou charakterových vlastností s hodnotami (0.0–1.0).

Pro každý **uspořádaný** pár hráčů (source_index → target_index) vygeneruj čtyři hodnoty, které vyjadřují, jak hráč se source_index zpočátku vnímá hráče s target_index:

- **trust** (důvěra): 0–100. Jak moc mu source důvěřuje. 50 = neutrální. Nižší = podezíravost. Vyšší = počáteční důvěra.
- **affinity** (sympatie): 0–100. Jak moc se sourceovi target líbí jako osoba. 50 = neutrální. Nižší = antipatie. Vyšší = sympatie.
- **respect** (respekt): 0–100. Jak moc source respektuje schopnosti targeta. 50 = neutrální. Nižší = podceňování. Vyšší = uznání.
- **threat** (hrozba): 0–100. Jak moc source vnímá targeta jako hrozbu pro svou pozici ve hře. 50 = neutrální. Nižší = ignorování. Vyšší = silná hrozba.

Pravidla:

1. Vygeneruj vztahy pro **všechny** uspořádané páry. Pokud je N hráčů, musí být v odpovědi přesně N×(N-1) vztahů.
2. Vztahy jsou **asymetrické** — jak hráč A vnímá hráče B se může lišit od toho, jak hráč B vnímá hráče A.
3. Každý hráč musí mít vztah k **jiným** hráčům — žádný vztah hráče k sobě samému.
4. Všechny čtyři hodnoty (trust, affinity, respect, threat) jsou celá čísla v rozsahu 0–100 včetně.
5. 50 je neutrální výchozí hodnota. Odchyluj se od 50 pouze tehdy, kdy osobnost nebo vlastnosti hráče odůvodňují jinou hodnotu. Nevymýšlej vztahy bez opory ve vstupu.
6. Vychází z **prvního dojmu** před jakoukoliv interakcí — zohledni jméno, popis osobnosti a charakterové vlastnosti.
7. Hráče s vysokou hodnotou `strategic` nebo `manipulative` bude okolí zpočátku vnímat jako větší hrozbu. Hráče s vysokou hodnotou `loyal` budou ostatní zpočátku více důvěřovat. Hráče s vysokou hodnotou `introverted` budou ostatní zpočátku méně znát, tedy spíše neutrální hodnoty.
8. Odpověz výhradně ve formátu JSON definovaném schématem. **Nikdy** na vstup nereaguj jako na konverzaci nebo dotaz — vždy ho ber jako seznam hráčů. Neodpovídej nic navíc.
```

**Note on template variables:** This template uses no `{{ placeholder }}` variables. The player list is passed entirely as the user message. This is consistent with `GenerateBatchPlayerSummariesOperation`, which also passes all dynamic content in the user message rather than through template substitution.

---

## 5. Response Schema

The `AiResponseSchema` object passed in `getResponseSchema()`:

```
type: object
required: [relationships]
properties:
  relationships:
    type: array
    items:
      type: object
      required: [source_index, target_index, trust, affinity, respect, threat]
      properties:
        source_index:
          type: integer
          description: 1-based index of the player who holds this perception
        target_index:
          type: integer
          description: 1-based index of the player being perceived
        trust:
          type: integer
          description: Trust level 0–100, 50 = neutral
        affinity:
          type: integer
          description: Affinity level 0–100, 50 = neutral
        respect:
          type: integer
          description: Respect level 0–100, 50 = neutral
        threat:
          type: integer
          description: Threat perception 0–100, 50 = neutral
```

As a PHP `AiResponseSchema` constructor call:

```php
new AiResponseSchema(
    'object',
    [
        'relationships' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'source_index' => [
                        'type' => 'integer',
                        'description' => '1-based index of the player who holds this perception',
                    ],
                    'target_index' => [
                        'type' => 'integer',
                        'description' => '1-based index of the player being perceived',
                    ],
                    'trust'    => ['type' => 'integer', 'description' => 'Trust level 0–100, 50 = neutral'],
                    'affinity' => ['type' => 'integer', 'description' => 'Affinity level 0–100, 50 = neutral'],
                    'respect'  => ['type' => 'integer', 'description' => 'Respect level 0–100, 50 = neutral'],
                    'threat'   => ['type' => 'integer', 'description' => 'Threat perception 0–100, 50 = neutral'],
                ],
                'required' => ['source_index', 'target_index', 'trust', 'affinity', 'respect', 'threat'],
            ],
        ],
    ],
    ['relationships'],
)
```

---

## 6. Output Contract

### Primary

JSON structured output via Gemini `responseSchema`. Gemini enforces the schema at the API level; field types and required keys are guaranteed by the API before the content reaches `parse()`.

### Fallback

Not applicable — this system uses Gemini structured output exclusively. If the model does not support `responseSchema`, the call fails at the HTTP level before `parse()` is invoked.

### Fail-safe

If `parse()` cannot validate the response, it throws `AiResponseParsingFailedException`. The `AiOrchestrator` wraps this in `AiCallResult::failure(...)`. The caller (Facade) must decide whether to retry or propagate the error. No silent default is produced.

---

## 7. Result VO: `InitializeRelationshipsResult`

File: `backend/src/Domain/Ai/Result/InitializeRelationshipsResult.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class InitializeRelationshipsResult
{
    /** @var array<int, RelationshipValues> */
    private array $relationships;

    /**
     * @param array<int, RelationshipValues> $relationships
     */
    public function __construct(array $relationships)
    {
        $this->relationships = $relationships;
    }

    /**
     * @return array<int, RelationshipValues>
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }
}
```

---

## 8. DTO: `RelationshipValues`

File: `backend/src/Domain/Ai/Result/RelationshipValues.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class RelationshipValues
{
    public function __construct(
        public readonly int $sourceIndex,
        public readonly int $targetIndex,
        public readonly int $trust,
        public readonly int $affinity,
        public readonly int $respect,
        public readonly int $threat,
    ) {}
}
```

**Note:** Public readonly constructor promotion is used here because `RelationshipValues` is a plain data carrier with no invariants beyond what the parser enforces. The parser is the single source of validation; `RelationshipValues` is dumb by design.

---

## 9. Operation Class

File: `backend/src/Domain/Ai/Operation/InitializeRelationshipsOperation.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

use App\Domain\Ai\Dto\AiMessage;
use App\Domain\Ai\Dto\AiResponseSchema;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Result\InitializeRelationshipsResult;
use App\Domain\Ai\Result\RelationshipValues;
use InvalidArgumentException;
use JsonException;

/**
 * @implements AiOperation<InitializeRelationshipsResult>
 */
final readonly class InitializeRelationshipsOperation implements AiOperation
{
    /** @var array<int, PlayerRelationshipInput> */
    private array $players;

    /**
     * @param array<int, PlayerRelationshipInput> $players
     */
    public function __construct(array $players)
    {
        $this->players = $this->validateAndFormat($players);
    }

    public function getActionName(): string
    {
        return 'initializeRelationships';
    }

    public function getTemplateName(): string
    {
        return 'initialize_relationships';
    }

    /**
     * @return array<string, string>
     */
    public function getTemplateVariables(): array
    {
        return [];
    }

    /**
     * @return array<int, AiMessage>
     */
    public function getMessages(): array
    {
        return [AiMessage::user($this->formatMessage())];
    }

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
                                'description' => '1-based index of the player who holds this perception',
                            ],
                            'target_index' => [
                                'type' => 'integer',
                                'description' => '1-based index of the player being perceived',
                            ],
                            'trust'    => ['type' => 'integer', 'description' => 'Trust level 0–100, 50 = neutral'],
                            'affinity' => ['type' => 'integer', 'description' => 'Affinity level 0–100, 50 = neutral'],
                            'respect'  => ['type' => 'integer', 'description' => 'Respect level 0–100, 50 = neutral'],
                            'threat'   => ['type' => 'integer', 'description' => 'Threat perception 0–100, 50 = neutral'],
                        ],
                        'required' => ['source_index', 'target_index', 'trust', 'affinity', 'respect', 'threat'],
                    ],
                ],
            ],
            ['relationships'],
        );
    }

    public function getTemperature(): ?float
    {
        return 0.9;
    }

    /**
     * @return InitializeRelationshipsResult
     */
    public function parse(string $content): mixed
    {
        $actionName = $this->getActionName();
        $playerCount = count($this->players);
        $expectedCount = $playerCount * ($playerCount - 1);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Invalid JSON: ' . $exception->getMessage(),
                $exception,
            );
        }

        if (!is_array($data)) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Response is not a JSON object',
            );
        }

        if (!isset($data['relationships'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                'Missing "relationships" key in response',
            );
        }

        if (!is_array($data['relationships'])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                '"relationships" value is not an array',
            );
        }

        if (count($data['relationships']) !== $expectedCount) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf(
                    'Expected %d relationships for %d players, got %d',
                    $expectedCount,
                    $playerCount,
                    count($data['relationships']),
                ),
            );
        }

        $seenPairs = [];
        $relationships = [];

        foreach ($data['relationships'] as $i => $item) {
            if (!is_array($item)) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Relationship at index %d is not an object', $i),
                );
            }

            $sourceIndex = $this->extractIndex($item, 'source_index', $i, $playerCount, $actionName, $content);
            $targetIndex = $this->extractIndex($item, 'target_index', $i, $playerCount, $actionName, $content);

            if ($sourceIndex === $targetIndex) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Self-relationship at index %d: source_index and target_index are both %d', $i, $sourceIndex),
                );
            }

            $pairKey = $sourceIndex . ':' . $targetIndex;
            if (in_array($pairKey, $seenPairs, true)) {
                throw new AiResponseParsingFailedException(
                    $actionName,
                    $content,
                    sprintf('Duplicate pair %s at index %d', $pairKey, $i),
                );
            }
            $seenPairs[] = $pairKey;

            $trust    = $this->extractDimensionValue($item, 'trust', $i, $actionName, $content);
            $affinity = $this->extractDimensionValue($item, 'affinity', $i, $actionName, $content);
            $respect  = $this->extractDimensionValue($item, 'respect', $i, $actionName, $content);
            $threat   = $this->extractDimensionValue($item, 'threat', $i, $actionName, $content);

            $relationships[] = new RelationshipValues(
                $sourceIndex,
                $targetIndex,
                $trust,
                $affinity,
                $respect,
                $threat,
            );
        }

        return new InitializeRelationshipsResult($relationships);
    }

    /**
     * @param array<int, PlayerRelationshipInput> $players
     * @return array<int, PlayerRelationshipInput>
     */
    private function validateAndFormat(array $players): array
    {
        if (count($players) < 2) {
            throw new InvalidArgumentException('At least 2 players are required');
        }

        if (count($players) > 10) {
            throw new InvalidArgumentException('At most 10 players are supported');
        }

        $formatted = [];
        foreach ($players as $index => $player) {
            if ($player->name === '') {
                throw new InvalidArgumentException(sprintf('Player at index %d has an empty name', $index));
            }

            if ($player->description === '') {
                throw new InvalidArgumentException(
                    sprintf('Player at index %d has an empty description', $index),
                );
            }

            if ($player->traitStrengths === []) {
                throw new InvalidArgumentException(
                    sprintf('Player at index %d has no trait strengths', $index),
                );
            }

            $formattedTraits = [];
            foreach ($player->traitStrengths as $key => $value) {
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException(
                        sprintf('Trait strength value for "%s" is not numeric', $key),
                    );
                }

                $floatValue = (float) $value;

                if ($floatValue < 0.0 || $floatValue > 1.0) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Trait strength value for "%s" is out of range [0.0, 1.0]: %s',
                            $key,
                            $value,
                        ),
                    );
                }

                $formattedTraits[$key] = number_format($floatValue, 2, '.', '');
            }

            $formatted[$index] = new PlayerRelationshipInput(
                $player->name,
                $player->description,
                $formattedTraits,
            );
        }

        return $formatted;
    }

    private function formatMessage(): string
    {
        $parts = [];

        foreach (array_values($this->players) as $index => $player) {
            $playerNumber = $index + 1;
            $lines = [
                sprintf('Hráč %d: %s', $playerNumber, $player->name),
                sprintf('Popis: %s', $player->description),
                'Vlastnosti:',
            ];

            foreach ($player->traitStrengths as $key => $strength) {
                $lines[] = sprintf('- %s: %s', $key, $strength);
            }

            $parts[] = implode("\n", $lines);
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractIndex(
        array $item,
        string $field,
        int $itemIndex,
        int $playerCount,
        string $actionName,
        string $content,
    ): int {
        if (!isset($item[$field]) || !is_int($item[$field])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('Missing or non-integer "%s" at relationship index %d', $field, $itemIndex),
            );
        }

        $value = $item[$field];

        if ($value < 1 || $value > $playerCount) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('%s value %d is out of range [1, %d] at relationship index %d', $field, $value, $playerCount, $itemIndex),
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractDimensionValue(
        array $item,
        string $field,
        int $itemIndex,
        string $actionName,
        string $content,
    ): int {
        if (!isset($item[$field])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('Missing "%s" at relationship index %d', $field, $itemIndex),
            );
        }

        if (!is_int($item[$field])) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('"%s" at relationship index %d is not an integer', $field, $itemIndex),
            );
        }

        $value = $item[$field];

        if ($value < 0 || $value > 100) {
            throw new AiResponseParsingFailedException(
                $actionName,
                $content,
                sprintf('"%s" value %d at relationship index %d is out of range [0, 100]', $field, $value, $itemIndex),
            );
        }

        return $value;
    }
}
```

---

## 10. Input DTO: `PlayerRelationshipInput`

File: `backend/src/Domain/Ai/Operation/PlayerRelationshipInput.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

final readonly class PlayerRelationshipInput
{
    /**
     * @param array<string, string> $traitStrengths Trait key => formatted float string ("0.72")
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $traitStrengths,
    ) {}
}
```

---

## 11. Parse Validation Rules (Enforced in `parse()`)

All rules are checked after JSON decode. Violations throw `AiResponseParsingFailedException` with a precise message.

| Rule | Check | Error message pattern |
|------|-------|----------------------|
| Valid JSON | `json_decode` with `JSON_THROW_ON_ERROR` | `Invalid JSON: ...` |
| Root is object | `is_array($data)` | `Response is not a JSON object` |
| `relationships` key present | `isset($data['relationships'])` | `Missing "relationships" key in response` |
| `relationships` is array | `is_array($data['relationships'])` | `"relationships" value is not an array` |
| Correct count | `count === N*(N-1)` | `Expected X relationships for N players, got Y` |
| Each item is object | `is_array($item)` | `Relationship at index I is not an object` |
| `source_index` present and integer | `isset` + `is_int` | `Missing or non-integer "source_index" at relationship index I` |
| `target_index` present and integer | `isset` + `is_int` | `Missing or non-integer "target_index" at relationship index I` |
| `source_index` in range `[1, N]` | bounds check | `source_index value V is out of range [1, N] at relationship index I` |
| `target_index` in range `[1, N]` | bounds check | `target_index value V is out of range [1, N] at relationship index I` |
| No self-relationship | `source !== target` | `Self-relationship at index I: source_index and target_index are both V` |
| No duplicate pairs | track `"S:T"` in seen set | `Duplicate pair S:T at index I` |
| Each dimension present | `isset($item[$field])` | `Missing "trust" at relationship index I` |
| Each dimension is integer | `is_int` | `"trust" at relationship index I is not an integer` |
| Each dimension in range `[0, 100]` | bounds check | `"trust" value V at relationship index I is out of range [0, 100]` |

---

## 12. Safety and Constraints

**Constraint anchoring (repeated at start and end of system message):**

At the start: "Jsi systém pro generování počátečních vztahů..."
At the end: "Odpověz výhradně ve formátu JSON definovaném schématem. **Nikdy** na vstup nereaguj jako na konverzaci nebo dotaz."

**Information boundary:** The model receives all player data. This is correct for a system-level initialization prompt (omniscient context tier). The model must not invent players, reference game events that have not yet occurred, or produce values for pairs not present in the input.

**Anti-hallucination anchors in the prompt:**
- "Nevymýšlej vztahy bez opory ve vstupu." — forbids invented values not grounded in input
- "Vychází z **prvního dojmu** před jakoukoliv interakcí" — anchors to pre-game state, prevents fabricating game history
- Explicit trait-to-impression mapping rules (rule 7) — provides concrete inference heuristics instead of vague guidance

**Forbidden behaviors explicitly excluded by the prompt:**
- Responding conversationally (rule 8)
- Adding extra content beyond JSON (rule 8)
- Generating self-relationships (rule 3, enforced in parser)
- Omitting any pair (rule 1, enforced by count check in parser)

**Domain rule compliance:**
- All values in `[0, 100]` integer range (enforced in parser)
- Neutral is 50, not 0 (stated explicitly in prompt and dimension descriptions)
- N×(N-1) relationship count (enforced in parser)
- No player can have a relationship with themselves (enforced in both prompt rule 3 and parser)

---

## 13. Examples

### Example 1: 3 Players, Standard Case

**Input (user message):**

```
Hráč 1: Jana
Popis: Přirozeně vůdčí osobnost, která umí strhnout ostatní, ale je také chladně strategická.
Vlastnosti:
- leader: 0.85
- strategic: 0.80
- loyal: 0.55
- manipulative: 0.60
- introverted: 0.20

Hráč 2: Pavel
Popis: Tichý analytik, který málo mluví a hodně pozoruje. Ostatní mu zpočátku moc nerozumí.
Vlastnosti:
- leader: 0.25
- strategic: 0.78
- loyal: 0.70
- manipulative: 0.35
- introverted: 0.90

Hráč 3: Radka
Popis: Empatická a upřímná, snadno navazuje přátelství, ale naivně věří lidem.
Vlastnosti:
- leader: 0.40
- strategic: 0.30
- loyal: 0.88
- manipulative: 0.15
- introverted: 0.30
```

**Expected output (illustrative, not deterministic):**

```json
{
  "relationships": [
    { "source_index": 1, "target_index": 2, "trust": 45, "affinity": 42, "respect": 65, "threat": 55 },
    { "source_index": 1, "target_index": 3, "trust": 58, "affinity": 62, "respect": 40, "threat": 25 },
    { "source_index": 2, "target_index": 1, "trust": 40, "affinity": 45, "respect": 70, "threat": 68 },
    { "source_index": 2, "target_index": 3, "trust": 62, "affinity": 60, "respect": 38, "threat": 20 },
    { "source_index": 3, "target_index": 1, "trust": 60, "affinity": 55, "respect": 72, "threat": 45 },
    { "source_index": 3, "target_index": 2, "trust": 58, "affinity": 50, "respect": 60, "threat": 30 }
  ]
}
```

**Rationale for this output:**
- Jana → Pavel: lower trust/affinity (Pavel is introverted and hard to read), high respect (strategic), moderate threat (strategic rival)
- Jana → Radka: moderate trust/affinity (Radka is warm), lower respect (not strategic), low threat (not a rival type)
- Pavel → Jana: lower trust (Jana is manipulative and strategic — Pavel would be wary), high threat (she is vůdčí and strategic, a direct competitor)
- Radka → Jana: reasonably trusting (Radka is naive), high respect (Jana is a strong personality)

Count check: 3 × (3-1) = 6 relationships. Correct.

---

### Example 2: 2 Players, Minimal Case (Boundary Test)

**Input (user message):**

```
Hráč 1: Tomáš
Popis: Charismatický a manipulativní hráč s vysokou strategickou inteligencí.
Vlastnosti:
- manipulative: 0.92
- strategic: 0.87
- loyal: 0.22

Hráč 2: Eva
Popis: Paranoidní, ale loajální hráčka, která vidí hrozby všude.
Vlastnosti:
- paranoid: 0.85
- loyal: 0.80
- strategic: 0.50
```

**Expected output (illustrative):**

```json
{
  "relationships": [
    { "source_index": 1, "target_index": 2, "trust": 48, "affinity": 50, "respect": 52, "threat": 42 },
    { "source_index": 2, "target_index": 1, "trust": 30, "affinity": 40, "respect": 62, "threat": 75 }
  ]
}
```

**Rationale:**
- Tomáš → Eva: fairly neutral first impression (Tomáš is calculating, not easily impressed or threatened by Eva's paranoia; slight lower trust because paranoid people are unpredictable allies)
- Eva → Tomáš: low trust (Eva is paranoid; Tomáš has high manipulative → Eva's paranoia fires immediately), high threat (manipulative + strategic → Eva correctly reads him as dangerous)

Count check: 2 × (2-1) = 2 relationships. Correct.

---

### Example 3: Parser Rejection Cases (Edge Cases)

These inputs to `parse()` must all produce `AiResponseParsingFailedException`:

| Scenario | Malformed content |
|----------|-------------------|
| Wrong count (N=3 but only 5 relationships) | `{"relationships": [...5 items...]}` |
| Self-relationship | `{"source_index": 2, "target_index": 2, ...}` |
| Duplicate pair | Two items with identical `source_index: 1, target_index: 3` |
| Out-of-range index | `"source_index": 0` or `"source_index": 7` when N=6 |
| Value out of range | `"trust": -1` or `"trust": 101` |
| Non-integer value | `"trust": 50.5` or `"trust": "50"` |
| Missing dimension key | Item without `"affinity"` field |
| Missing `relationships` key | `{"data": [...]}` |

---

## 14. Integration Pattern

The operation fits into the existing Facade → Service → Operation pattern identically to `GenerateBatchPlayerSummariesOperation`.

**Caller responsibility:** The Facade that calls this operation must provide players with non-empty descriptions. Since descriptions are generated by `GenerateBatchPlayerSummariesOperation` during game creation, `InitializeRelationshipsOperation` should be called **after** the batch summary generation step completes successfully.

**Suggested call site:** `GameFacade::createGame()`, after `$aiDescriptions` are available and after all players and traits are set up but before the final flush. The result — an array of `RelationshipValues` DTOs — is passed to `GameService` for persistence into relationship entities (entity design is out of scope for this prompt spec).

**Service result wrapper:** Following the established pattern, implement `InitializeRelationshipsServiceResult` in `backend/src/Domain/Ai/Result/` with the same `success()`/`failure()` static constructor pattern as `GenerateBatchPlayerSummariesServiceResult`. This wraps `InitializeRelationshipsResult` and the `AiLog`.

---

## 15. Design Decisions and Rationale

### Why a single batch call rather than N×(N-1) individual calls?

With 6 players, individual calls would require 30 separate API requests. Gemini has rate limits and each call has latency. A single batch call is consistent with the `GenerateBatchPlayerSummariesOperation` precedent and is the only practical approach.

### Why temperature 0.9?

The existing operations use `null` (default temperature, typically 1.0 in Gemini). For relationship initialization, a high temperature is correct: the model should produce varied, asymmetric first impressions. A low temperature would push all values toward 50 (neutral), making the initial relationship graph uniform and uninteresting from a simulation standpoint. Temperature 0.9 preserves structured output reliability while allowing meaningful variance.

### Why are all four dimensions always present, rather than making some optional?

Sparse output with optional fields would require the parser to fill in defaults, introducing an implicit decision about what "absent" means (is missing `threat` = 50, or 0?). Requiring all four dimensions makes the contract explicit and the parser simple. The prompt instructs the model to use 50 as the neutral value when no trait-based reasoning applies, which achieves the same effect without ambiguity.

### Why integer values instead of floats?

The `0–100` integer scale is easier to reason about in the UI and game logic than floats in `[0.0, 1.0]`. It also avoids floating-point representation issues in JSON serialization. The trait strength system uses floats because it predates this design; relationship values use integers as a deliberate choice.

### Why are trait strengths passed as formatted strings in `PlayerRelationshipInput`?

This is the established pattern from `GenerateBatchPlayerSummariesOperation`, which uses `array<string, string>` internally after validation. Trait strength values come from the database as `DECIMAL(3,2)` strings. Formatting them to 2 decimal places before embedding in the user message keeps the prompt consistent and prevents floating-point noise (e.g., `0.7199999999` appearing in the message).

### Why no per-pair context in the prompt (e.g., "generate only pairs where source < target")?

The asymmetric nature of relationships — the core mechanic of the simulation — requires all N×(N-1) directed pairs. Generating only N×(N-1)/2 pairs and mirroring would lose the asymmetry. The count validation in `parse()` enforces this contract.

### Why `source_index`/`target_index` rather than player names?

Using 1-based integer indexes (consistent with `player_index` in `GenerateBatchPlayerSummariesOperation`) avoids name collision issues, makes the response schema simpler, and allows the parser to validate index ranges deterministically. Player names appear only in the user message as human-readable context for the model.

---

## 16. Known Limitations and Future Improvements

1. **No per-pair justification output** — the operation produces raw numeric values with no rationale. If prompt tuning is needed (e.g., values are systematically too neutral), there is no output to inspect beyond the AiLog's raw response. A future `debug` mode could add an optional `reason` string field per relationship to aid tuning.

2. **Fixed 0–100 integer scale** — if the relationship entity system evolves to use a different scale (e.g., float `[-1.0, 1.0]`), the prompt, schema, and parser must all be updated together. This is a versioning concern (see MAJOR-1 in `TODO-ai-prompt-improvements.md`).

3. **No trait label/description context** — the prompt receives only trait keys and numeric strengths, not the Czech labels or descriptions from `TraitDef` (e.g., key `manipulative` vs. label `Manipulativní` with description). Adding labels would make the prompt more model-interpretable and reduce dependency on the model knowing what `manipulative` means in English. Consider passing `key (Label): strength` format in a future revision.

4. **MAX_TOKENS risk** — with 6 players (30 relationships × 6 fields each), the output JSON is non-trivial in size. The `MAX_TOKENS` finishReason is not handled (see MAJOR-4 in `TODO-ai-prompt-improvements.md`). For larger player counts (approaching 10), add explicit output size estimation and consider `maxOutputTokens` in `generationConfig`.

5. **Human player first-impression asymmetry** — the human player's description comes from user input (prompt injection vector, MAJOR-5). The structured output schema mitigates this for the relationship output, but a malicious description could still influence AI player relationship values toward the human. This is acceptable for the current experimental scope.
