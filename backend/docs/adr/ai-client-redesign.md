# ADR: Redesign AI klienta -- Gemini REST API

**Status:** Proposed

**Datum:** 2026-02-13

**Kontext:** Stávající `AiClient` používá lokální OpenAI-kompatibilní server přes Symfony HttpClient. Míchá infrastrukturu (HTTP volání, Doctrine persist/flush) s business logikou (parsování JSON, extrakce obsahu) v jedné třídě. Chybí sledování tokenů, status logů, strukturovaný výstup a typované Result objekty. Knihovna `gemini-api-php/client` je nainstalovaná, ale nepoužívá se -- a chybí jí podpora `usageMetadata` i strukturovaného výstupu.

**Rozhodnutí:** Kompletní přepis AI vrstvy s přímým napojením na Google Gemini REST API přes Symfony HttpClient. Odstranění `gemini-api-php/client` z composer.json. Striktní separace infrastruktury a business logiky podle vzoru Controller -> Facade -> Service.

---

## 1. Zdůvodnění volby přímého Gemini REST API

### Proč NE `gemini-api-php/client`

1. **Chybí `usageMetadata`** -- `GenerateContentResponse` v knihovně exponuje pouze `candidates` a `promptFeedback`, ne tokeny
2. **Chybí strukturovaný výstup** -- nepodporuje `responseMimeType` + `responseSchema` v `generationConfig`
3. **System instructions pouze přes beta verzi** -- nestabilní API surface
4. **Komunitní údržba** -- není oficiální Google knihovna, zpožděné aktualizace

### Proč ANO přímé HTTP volání

1. **Plná kontrola nad `usageMetadata`** -- `promptTokenCount`, `candidatesTokenCount`, `totalTokenCount`
2. **Strukturovaný výstup** -- `responseMimeType: "application/json"` + `responseSchema`
3. **Symfony HttpClient už je v projektu** -- žádná nová závislost
4. **Přímý přístup k `modelVersion`** a dalším metadatům odpovědi
5. **Plná kontrola nad error handlingem** -- rate limits, safety blocks, API chyby

### Alternativy

1. **google-gemini-php/client** -- zamítnuto (viz výše)
2. **Vertex AI SDK** -- zamítnuto, overhead pro jednoduchý projekt, vyžaduje GCP auth
3. **OpenAI-kompatibilní proxy** -- zamítnuto, ztrácíme Gemini-specifické features (structured output, usageMetadata)

---

## 2. Architektonické zařazení ve vrstvách

### Klíčové rozhodnutí: Kde sedí GeminiClient?

`GeminiClient` je **infrastrukturní služba** -- provádí HTTP volání. Podle našeho vzoru Controller -> Facade -> Service by infrastruktura měla být v Facade vrstvě. Ale `GeminiClient` není Facade v klasickém smyslu (neorchestruje více kroků, nevolá flush). Je to **specializovaný infrastrukturní adapter** -- obálka kolem externího API.

**Rozhodnutí:** `GeminiClient` je infrastrukturní služba na úrovni Facade, ale s vlastním rozhraním (`GeminiClient`). Facade (např. `AiPlayerFacade`) ho používá jako závislost. Service vrstva ho nevidí.

```
Controller -> Facade -> Service (čistá logika)
                  |
                  +-> GeminiClient (infrastruktura -- HTTP)
                  +-> EntityManager (infrastruktura -- persistence)
```

### Pravidla

- **GeminiClient** -- HTTP volání, parsování raw odpovědi, vrací typovaný `GeminiResponse`
- **Facade** (např. `AiPlayerFacade`) -- orchestruje: sestaví prompt, zavolá GeminiClient, uloží AiLog, zavolá Service pro business zpracování, flush
- **Service** (např. `AiResponseParser`) -- čistá logika: validace, transformace strukturovaných dat na Result objekty
- **AiLog entita** -- vytvořena ve Facade PŘED voláním, aktualizována PO odpovědi

---

## 3. Adresářová struktura

```
src/Domain/Ai/
├── Client/
│   ├── GeminiClient.php              # Interface pro testovatelnost
│   ├── HttpGeminiClient.php          # Infrastrukturní adapter (HTTP volání)
│   └── GeminiConfiguration.php       # Value Object -- API key, model, base URL
├── Dto/
│   ├── AiMessage.php                 # VO -- role + content pro konverzaci
│   ├── AiRequest.php                 # VO -- kompletní request pro AI volání
│   └── AiResponseSchema.php         # VO -- JSON schema pro strukturovaný výstup
├── Result/
│   ├── AiResponse.php               # Result -- zpracovaná odpověď z Gemini
│   └── TokenUsage.php               # VO -- promptTokenCount, candidatesTokenCount, totalTokenCount
├── Log/
│   ├── AiLog.php                     # Entity (přepracovaná)
│   ├── AiLogRepository.php           # Repository
│   └── AiLogStatus.php              # Enum -- pending/success/error
├── Exceptions/
│   ├── AiRequestFailedException.php         # HTTP/síťová chyba
│   ├── AiResponseBlockedBySafetyException.php  # Safety filtr zablokoval odpověď
│   ├── AiResponseParsingFailedException.php    # Odpověď nelze naparsovat
│   └── AiRateLimitExceededException.php        # Rate limit (429)
├── Prompt/
│   ├── PromptLoader.php             # Service -- načítání a hydratace .md promptů
│   └── templates/                   # Adresář se šablonami promptů
│       ├── generate_player_traits.md
│       └── generate_player_summary.md
├── Service/
│   └── AiResponseParser.php         # Service -- čistá logika parsování/validace
├── AiPlayerFacade.php               # Facade (přepracovaná)
└── AiClient.php                      # SMAZAT (nahrazeno GeminiClient)
```

**Odstraněné soubory:**
- `AiClient.php` -- nahrazeno `Client/HttpGeminiClient.php`
- Prázdné placeholder adresáře `Gemini/`, `Request/`, `Response/`

**Odstraněné composer závislosti:**
- `google-gemini-php/client` (^2.7.4)
- `guzzlehttp/guzzle` (^7.10) -- pokud není používán jinde
- `nyholm/psr7` (^1.8.2) -- pokud není používán jinde
- `psr/http-client` (^1.0.3) -- pokud není používán jinde

---

## 4. Konfigurace (.env)

```dotenv
###> ai/gemini ###
GEMINI_API_KEY=your-api-key-here
GEMINI_MODEL=gemini-2.5-flash
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_DEFAULT_TEMPERATURE=0.6
###< ai/gemini ###
```

### Symfony services.yaml

```yaml
parameters:
    gemini.api_key: '%env(GEMINI_API_KEY)%'
    gemini.model: '%env(GEMINI_MODEL)%'
    gemini.base_url: '%env(GEMINI_BASE_URL)%'
    gemini.default_temperature: '%env(float:GEMINI_DEFAULT_TEMPERATURE)%'

services:
    App\Domain\Ai\Client\GeminiConfiguration:
        arguments:
            $apiKey: '%gemini.api_key%'
            $model: '%gemini.model%'
            $baseUrl: '%gemini.base_url%'
            $defaultTemperature: '%gemini.default_temperature%'

    App\Domain\Ai\Client\GeminiClient:
        alias: App\Domain\Ai\Client\HttpGeminiClient
```

---

## 5. Specifikace tříd

### 5.1 Value Objects a DTOs

#### GeminiConfiguration

```
Soubor: src/Domain/Ai/Client/GeminiConfiguration.php
Typ: final readonly class
Účel: Drží konfiguraci pro Gemini API -- immutable, injektovaná přes DI container.

Vlastnosti:
  - apiKey: string
  - model: string (název modelu, např. "gemini-2.5-flash")
  - baseUrl: string (base URL API, bez trailing slash)
  - defaultTemperature: float

Konstruktor:
  public function __construct(
      string $apiKey,
      string $model,
      string $baseUrl,
      float $defaultTemperature,
  )

Metody:
  public function getEndpointUrl(): string
    -- Vrátí: "{baseUrl}/models/{model}:generateContent"
  + standardní gettery pro všechny vlastnosti
```

#### AiMessage

```
Soubor: src/Domain/Ai/Dto/AiMessage.php
Typ: final readonly class
Účel: Reprezentuje jednu zprávu v konverzaci s AI. Mapuje se na
      Gemini "contents" element.

Vlastnosti:
  - role: string ("user" nebo "model")
  - content: string

Konstruktor:
  public function __construct(string $role, string $content)

Statické factory metody:
  public static function user(string $content): self
  public static function model(string $content): self
```

#### AiResponseSchema

```
Soubor: src/Domain/Ai/Dto/AiResponseSchema.php
Typ: final readonly class
Účel: Popisuje JSON schema pro strukturovaný výstup z Gemini.
      Mapuje se na generationConfig.responseSchema.

Vlastnosti:
  - type: string ("object", "array", "string", ...)
  - properties: array<string, array<string, mixed>> (definice vlastností)
  - required: array<int, string> (povinné klíče)
  - description: ?string

Konstruktor:
  /**
   * @param array<string, array<string, mixed>> $properties
   * @param array<int, string> $required
   */
  public function __construct(
      string $type,
      array $properties,
      array $required,
      ?string $description = null,
  )

Metoda:
  /**
   * @return array<string, mixed>
   */
  public function toArray(): array
    -- Vrátí schema jako asociativní pole pro JSON serializaci do Gemini requestu.
```

#### AiRequest

```
Soubor: src/Domain/Ai/Dto/AiRequest.php
Typ: final readonly class
Účel: Kompletní popis AI požadavku -- vše co je potřeba k sestavení
      HTTP volání. Neobsahuje infrastrukturní detaily (URL, API key).

Vlastnosti:
  - actionName: string (identifikátor akce pro logování, např. "generatePlayerTraits")
  - systemInstruction: string (systémový prompt)
  - messages: array<int, AiMessage> (uživatelské zprávy)
  - temperature: ?float (null = použít default z konfigurace)
  - responseSchema: ?AiResponseSchema (null = volný text, ne-null = strukturovaný JSON)

Konstruktor:
  /**
   * @param array<int, AiMessage> $messages
   */
  public function __construct(
      string $actionName,
      string $systemInstruction,
      array $messages,
      ?float $temperature = null,
      ?AiResponseSchema $responseSchema = null,
  )
```

#### TokenUsage

```
Soubor: src/Domain/Ai/Result/TokenUsage.php
Typ: final readonly class
Účel: Value Object pro spotřebu tokenů z jednoho AI volání.

Vlastnosti:
  - promptTokenCount: int
  - candidatesTokenCount: int
  - totalTokenCount: int

Konstruktor:
  public function __construct(
      int $promptTokenCount,
      int $candidatesTokenCount,
      int $totalTokenCount,
  )
```

#### AiResponse

```
Soubor: src/Domain/Ai/Result/AiResponse.php
Typ: final readonly class
Účel: Result objekt -- zpracovaná odpověď z Gemini. Vrací GeminiClient,
      konzumuje Facade.

Vlastnosti:
  - content: string (textový obsah odpovědi -- raw text nebo JSON string)
  - tokenUsage: TokenUsage
  - durationMs: int (doba trvání HTTP volání v milisekundách)
  - modelVersion: string (verze modelu z odpovědi)
  - rawResponseJson: string (kompletní surová odpověď pro logování)
  - finishReason: string (např. "STOP", "MAX_TOKENS", "SAFETY")

Konstruktor:
  public function __construct(
      string $content,
      TokenUsage $tokenUsage,
      int $durationMs,
      string $modelVersion,
      string $rawResponseJson,
      string $finishReason,
  )
```

### 5.2 Enum

#### AiLogStatus

```
Soubor: src/Domain/Ai/Log/AiLogStatus.php
Typ: enum (string backed)

Případy:
  case Pending = 'pending';
  case Success = 'success';
  case Error = 'error';
```

### 5.3 Exceptions

#### AiRequestFailedException

```
Soubor: src/Domain/Ai/Exceptions/AiRequestFailedException.php
Typ: final class extends RuntimeException

Kontext: HTTP volání selhalo (síťová chyba, timeout, 5xx).

Vlastnosti:
  - actionName: string
  - httpStatusCode: ?int (null při síťové chybě bez odpovědi)

Konstruktor:
  public function __construct(
      string $actionName,
      ?int $httpStatusCode,
      string $detail,
      ?Throwable $previous = null,
  )

  Message: "AI request '%s' failed with HTTP %d: %s" nebo
           "AI request '%s' failed: %s" (bez HTTP kódu)

Gettery:
  public function getActionName(): string
  public function getHttpStatusCode(): ?int
```

#### AiRateLimitExceededException

```
Soubor: src/Domain/Ai/Exceptions/AiRateLimitExceededException.php
Typ: final class extends RuntimeException

Kontext: Gemini vrátil HTTP 429.

Vlastnosti:
  - actionName: string

Konstruktor:
  public function __construct(string $actionName, ?Throwable $previous = null)

  Message: "AI rate limit exceeded for action '%s'"

Getter:
  public function getActionName(): string
```

#### AiResponseBlockedBySafetyException

```
Soubor: src/Domain/Ai/Exceptions/AiResponseBlockedBySafetyException.php
Typ: final class extends RuntimeException

Kontext: Gemini zablokoval odpověď kvůli safety filtru (finishReason = "SAFETY"
         nebo prázdní candidates).

Vlastnosti:
  - actionName: string

Konstruktor:
  public function __construct(string $actionName, ?Throwable $previous = null)

  Message: "AI response for action '%s' was blocked by safety filter"

Getter:
  public function getActionName(): string
```

#### AiResponseParsingFailedException

```
Soubor: src/Domain/Ai/Exceptions/AiResponseParsingFailedException.php
Typ: final class extends RuntimeException

Kontext: Odpověď přišla, ale nelze ji naparsovat (nevalidní JSON,
         chybějící klíče, neočekávaná struktura).

Vlastnosti:
  - actionName: string
  - rawContent: string

Konstruktor:
  public function __construct(
      string $actionName,
      string $rawContent,
      string $detail,
      ?Throwable $previous = null,
  )

  Message: "Failed to parse AI response for action '%s': %s"

Gettery:
  public function getActionName(): string
  public function getRawContent(): string
```

### 5.4 AiLog entita (přepracovaná)

```
Soubor: src/Domain/Ai/Log/AiLog.php
Typ: final class (Doctrine entity)
Tabulka: ai_log

Sloupce:
  - id: Uuid (PK, v7, generováno v konstruktoru)
  - createdAt: DateTimeImmutable (Types::DATETIME_IMMUTABLE)
  - modelName: string (length: 100) -- název modelu z konfigurace
  - actionName: string (length: 255) -- identifikátor akce
  - systemPrompt: text -- systémový prompt
  - userPrompt: text -- uživatelský prompt (první zpráva)
  - requestJson: text -- kompletní HTTP request body jako JSON
  - status: AiLogStatus (Types::STRING, length: 20, enumType: AiLogStatus::class) -- default: pending
  - responseJson: ?text -- kompletní HTTP response body (null = pending)
  - returnContent: ?text -- extrahovaný obsah odpovědi (null = pending/error)
  - promptTokenCount: ?int (Types::INTEGER, nullable: true) -- tokeny promptu
  - candidatesTokenCount: ?int (Types::INTEGER, nullable: true) -- tokeny odpovědi
  - totalTokenCount: ?int (Types::INTEGER, nullable: true) -- celkový počet tokenů
  - durationMs: ?int (Types::INTEGER, nullable: true) -- doba trvání v ms
  - modelVersion: ?string (length: 100, nullable: true) -- verze modelu z odpovědi
  - finishReason: ?string (length: 50, nullable: true) -- důvod ukončení generování
  - temperature: float (Types::FLOAT) -- použitá teplota
  - errorMessage: ?text (nullable: true) -- chybová zpráva při selhání

Konstruktor:
  public function __construct(
      string $modelName,
      DateTimeImmutable $createdAt,
      string $actionName,
      string $systemPrompt,
      string $userPrompt,
      string $requestJson,
      float $temperature,
  )
  -- Nastaví status = AiLogStatus::Pending
  -- Generuje id = Uuid::v7()

Sémantické metody:
  public function recordSuccess(AiResponse $response): void
    -- Nastaví status = AiLogStatus::Success
    -- Zapíše responseJson, returnContent, token counts, durationMs,
       modelVersion, finishReason z AiResponse

  public function recordError(string $errorMessage, ?int $durationMs = null): void
    -- Nastaví status = AiLogStatus::Error
    -- Zapíše errorMessage a volitelně durationMs

Gettery:
  + Standardní getter pro každý sloupec
```

**Změny oproti stávajícímu AiLog:**
- Odstraněn `apiUrl` (zbytečný -- URL je v konfiguraci, ne v logu)
- Přidán `status` (AiLogStatus enum)
- Přidány `promptTokenCount`, `candidatesTokenCount`, `totalTokenCount`
- Přidán `modelVersion` (z Gemini odpovědi)
- Přidán `finishReason`
- Přidán `temperature`
- Přidán `errorMessage`
- Přejmenován `duration` na `durationMs` (explicitní jednotka)
- Všechna pole jsou non-nullable v konstruktoru (kromě response-related, které jsou nullable do doby odpovědi)
- `recordResponse()` nahrazeno dvěma sémantickými metodami: `recordSuccess()` a `recordError()`

### 5.5 GeminiClient

```
Soubor: src/Domain/Ai/Client/GeminiClient.php
Typ: interface

Účel: Abstrakce nad Gemini HTTP voláním. Umožňuje mockování v testech.
      Toto je jediné místo v doméně, které se přímo dotýká HTTP.

Metody:

  /**
   * Odešle request na Gemini API a vrátí zpracovanou odpověď.
   *
   * @throws AiRequestFailedException         HTTP/síťová chyba
   * @throws AiRateLimitExceededException      HTTP 429
   * @throws AiResponseBlockedBySafetyException  Safety filtr
   * @throws AiResponseParsingFailedException    Nevalidní odpověď
   */
  public function request(AiRequest $aiRequest): AiResponse;
```

### 5.6 HttpGeminiClient (implementace)

```
Soubor: src/Domain/Ai/Client/HttpGeminiClient.php
Typ: final class implements GeminiClient

Závislosti (constructor injection):
  - httpClient: HttpClientInterface (Symfony)
  - configuration: GeminiConfiguration

Metoda request(AiRequest $aiRequest): AiResponse:

  Orchestrace:
  1. Sestavit request body (viz sekce 6)
  2. Změřit čas: $startTime = hrtime(true)
  3. Provést HTTP POST na configuration->getEndpointUrl()
     s hlavičkami:
       - Content-Type: application/json
       - x-goog-api-key: configuration->getApiKey()
     a query parametrem: key={apiKey}
  4. Změřit dobu: $durationMs = (int) round((hrtime(true) - $startTime) / 1_000_000)
  5. Zpracovat HTTP status:
     - 429 -> throw AiRateLimitExceededException
     - 4xx/5xx -> throw AiRequestFailedException
  6. Naparsovat JSON odpověď
  7. Extrahovat candidates[0].content.parts[0].text
  8. Zkontrolovat finishReason -- "SAFETY" -> throw AiResponseBlockedBySafetyException
  9. Extrahovat usageMetadata -> TokenUsage
  10. Extrahovat modelVersion
  11. Vrátit AiResponse

  Poznámka ke Gemini auth: API key se předává jako query parameter `key`
  NEBO jako header `x-goog-api-key`. Použijeme query parameter -- je
  to jednodušší a odpovídá oficiální dokumentaci.

Privátní metody:

  /**
   * @return array<string, mixed>
   */
  private function buildRequestBody(AiRequest $aiRequest): array
    -- Sestaví pole pro JSON serializaci (viz sekce 6)

  /**
   * @param array<string, mixed> $responseData
   */
  private function parseResponse(array $responseData, int $durationMs, string $rawJson): AiResponse
    -- Extrahuje content, token usage, model version z odpovědi
```

### 5.7 AiResponseParser (Service -- čistá logika)

```
Soubor: src/Domain/Ai/Service/AiResponseParser.php
Typ: final class

Závislosti: žádné (čistý service)

Účel: Parsuje a validuje strukturovaný JSON obsah z AI odpovědi.
      Transformuje surový JSON string na typované Result objekty
      specifické pro daný use-case.

Metody:

  /**
   * Parsuje odpověď s trait scores a summary.
   *
   * Očekávaná struktura:
   * {
   *   "traits": {"key": 0.8, ...},
   *   "summary": "..."
   * }
   *
   * @param array<int, TraitDef> $availableTraits
   * @return GenerateTraitsResult
   * @throws AiResponseParsingFailedException
   */
  public function parseGenerateTraitsResponse(
      string $content,
      array $availableTraits,
      string $actionName,
  ): GenerateTraitsResult

  /**
   * Parsuje odpověď se summary.
   *
   * Očekávaná struktura:
   * {
   *   "summary": "..."
   * }
   *
   * @throws AiResponseParsingFailedException
   */
  public function parseGenerateSummaryResponse(
      string $content,
      string $actionName,
  ): GenerateSummaryResult
```

#### GenerateTraitsResult

```
Soubor: src/Domain/Ai/Result/GenerateTraitsResult.php
Typ: final readonly class

Vlastnosti:
  - traitScores: array<string, float> (klíč = trait key, hodnota = strength 0.0-1.0)
  - summary: string

Konstruktor:
  /**
   * @param array<string, float> $traitScores
   */
  public function __construct(array $traitScores, string $summary)
```

#### GenerateSummaryResult

```
Soubor: src/Domain/Ai/Result/GenerateSummaryResult.php
Typ: final readonly class

Vlastnosti:
  - summary: string

Konstruktor:
  public function __construct(string $summary)
```

### 5.8 AiPlayerFacade (přepracovaná)

```
Soubor: src/Domain/Ai/AiPlayerFacade.php
Typ: final class

Závislosti (constructor injection):
  - geminiClient: GeminiClient
  - entityManager: EntityManagerInterface
  - aiResponseParser: AiResponseParser (Service)
  - promptLoader: PromptLoader (Service)
  - configuration: GeminiConfiguration

Účel: Orchestruje AI volání pro generování hráčských vlastností.
      Vytváří AiLog, volá GeminiClient, parsuje odpověď, persistuje log.
```

#### Metoda: generatePlayerTraitsFromDescription

```
Signatura:
  /**
   * @param array<int, TraitDef> $traits
   */
  public function generatePlayerTraitsFromDescription(
      string $description,
      array $traits,
  ): GenerateTraitsResult

Orchestrace:
  1. $now = new DateTimeImmutable()
  2. $systemPrompt = $this->promptLoader->load('generate_player_traits', [
         'traitKeys' => implode(', ', array_map(fn(TraitDef $t) => $t->getKey(), $traits)),
     ])
  3. Sestavit AiResponseSchema pro strukturovaný výstup:
     - type: "object"
     - properties: traits (object s float hodnotami), summary (string)
     - required: ["traits", "summary"]
  4. Vytvořit AiRequest s:
     - actionName: "generatePlayerTraitsFromDescription"
     - systemInstruction: systémový prompt
     - messages: [AiMessage::user($description)]
     - responseSchema: schema z kroku 3
  5. Vytvořit AiLog (pending):
     - new AiLog($configuration->getModel(), $now, actionName, systemPrompt, $description, requestJson, temperature)
  6. $entityManager->persist($aiLog)
  7. TRY:
     a. $aiResponse = $geminiClient->request($aiRequest)
     b. $aiLog->recordSuccess($aiResponse)
     c. $result = $aiResponseParser->parseGenerateTraitsResponse(
            $aiResponse->content, $traits, "generatePlayerTraitsFromDescription"
        )
     d. $entityManager->flush()
     e. return $result
  8. CATCH (AiRequestFailedException | AiRateLimitExceededException |
           AiResponseBlockedBySafetyException | AiResponseParsingFailedException $exception):
     a. $aiLog->recordError($exception->getMessage())
     b. $entityManager->flush()
     c. throw $exception (re-throw -- Facade loguje, ale nepolyká)
```

#### Metoda: generatePlayerTraitsSummaryDescription

```
Signatura:
  /**
   * @param array<string, string> $traitStrengths
   */
  public function generatePlayerTraitsSummaryDescription(
      array $traitStrengths,
  ): GenerateSummaryResult

Orchestrace:
  1. $now = new DateTimeImmutable()
  2. $systemPrompt = $this->promptLoader->load('generate_player_summary')
  3. Sestavit userContent z traitStrengths (key: value\n)
  4. Sestavit AiResponseSchema:
     - type: "object"
     - properties: summary (string)
     - required: ["summary"]
  5. Vytvořit AiRequest
  6. Vytvořit AiLog (pending) a persist
  7. TRY:
     a. Zavolat GeminiClient
     b. recordSuccess na AiLog
     c. Parsovat přes AiResponseParser
     d. flush + return
  8. CATCH: recordError, flush, re-throw
```

---

## 6. Prompt management -- externalizované šablony v Markdownu

### 6.1 Motivace

Systémové prompty jsou nejčastěji měněná část AI integrace. Psát je inline v PHP kódu je nepraktické:
- Markdown formátování se v PHP heredocu špatně čte a edituje
- Změna promptu vyžaduje změnu PHP souboru → PHPCS/PHPStan/testy
- Prompty jsou přirozený obsah, ne kód -- patří do datových souborů
- Prompt engineer (nebo i ne-programátor) by měl mít možnost prompt upravit bez znalosti PHP

### 6.2 Rozhodnutí: Markdown soubory s placeholder substitucí

Prompty se ukládají jako `.md` soubory v adresáři `src/Domain/Ai/Prompt/templates/`. Každý soubor = jeden systémový prompt. Proměnné se vkládají přes jednoduché `{{ placeholder }}` placeholdery, které `PromptLoader` nahradí za runtime hodnoty.

**Proč ne Twig?** Twig je silný templating engine, ale pro prompty je overkill. Prompty nepotřebují loops, conditions, includes ani escape. Jednoduchý `str_replace` na `{{ name }}` placeholdery stačí a nevnáší žádnou závislost.

**Proč ne YAML?** YAML je vhodný pro strukturovaná data, ale multiline Markdown text se v něm špatně formátuje (odsazení, pipe operátor). Čistý `.md` soubor je přirozenější a editor ho zobrazí se syntax highlighting.

### 6.3 Struktura šablony

Každý `.md` soubor obsahuje čistý text systémového promptu. Proměnné se zapisují jako `{{ variableName }}`.

#### Příklad: `generate_player_traits.md`

```markdown
Jsi systém pro generování psychologických charakteristik hráčů reality show Survivor.

Na základě popisu osobnosti vygeneruj skóre (0.0–1.0) pro následující charakterové vlastnosti: {{ traitKeys }}.

Každá hodnota musí být mezi 0.0 a 1.0, zapsaná jako float se dvěma desetinnými místy.

Poté přidej krátké shrnutí hráčovy osobnosti ve formě jednoho až dvou **jasně oddělených vět**. Nepoužívej středník – věty ukončuj běžnou tečkou. Shrnutí napiš lidským jazykem.

**Nikdy** na vstup nereaguj jako na konverzaci nebo dotaz – vždy ho ber jako popis hráče. Neodpovídej nic navíc.
```

#### Příklad: `generate_player_summary.md`

```markdown
Jsi systém pro generování popisu psychologické charakteristiky hráče reality show Survivor.

Na základě předaných charakterových vlastností a jejich hodnot (0.0–1.0) vygeneruj krátké shrnutí hráčovy osobnosti ve formě jednoho až dvou **jasně oddělených vět**. Nepoužívej středník – věty ukončuj běžnou tečkou. Shrnutí napiš lidským jazykem.
```

**Poznámka:** Instrukce o JSON formátu (`responseMimeType` + `responseSchema`) se do promptu NEPÍŠÍ -- o strukturovaný výstup se stará `AiResponseSchema` v `AiRequest`. Gemini to vynucuje na API úrovni, prompt se tak zjednoduší.

### 6.4 PromptLoader

```
Soubor: src/Domain/Ai/Prompt/PromptLoader.php
Typ: final readonly class
Účel: Načítá .md šablony ze souborového systému a nahrazuje placeholdery
      za runtime hodnoty.

Závislosti: žádné (čistý Service, cestu k adresáři dostane přes DI)

Konstruktor:
  public function __construct(string $templateDirectory)
  -- $templateDirectory: absolutní cesta k adresáři s .md šablonami
  -- Injektovaná přes services.yaml: '%kernel.project_dir%/src/Domain/Ai/Prompt/templates'

Metody:

  /**
   * Načte šablonu a nahradí placeholdery.
   *
   * @param string $templateName  Název šablony bez přípony (např. "generate_player_traits")
   * @param array<string, string> $variables  Klíč = placeholder název, hodnota = náhrada
   * @return string  Finální text promptu
   * @throws PromptTemplateNotFoundException  Šablona neexistuje
   */
  public function load(string $templateName, array $variables = []): string

Interní logika:
  1. $path = $this->templateDirectory . '/' . $templateName . '.md'
  2. Ověřit, že soubor existuje -> PromptTemplateNotFoundException
  3. $content = file_get_contents($path)
  4. Pro každý $key => $value v $variables:
     $content = str_replace('{{ ' . $key . ' }}', $value, $content)
  5. return trim($content)
```

### 6.5 PromptTemplateNotFoundException

```
Soubor: src/Domain/Ai/Exceptions/PromptTemplateNotFoundException.php
Typ: final class extends RuntimeException

Konstruktor:
  public function __construct(string $templateName)

  Message: "Prompt template '%s' not found"
```

### 6.6 Konfigurace v services.yaml

```yaml
services:
    App\Domain\Ai\Prompt\PromptLoader:
        arguments:
            $templateDirectory: '%kernel.project_dir%/src/Domain/Ai/Prompt/templates'
```

### 6.7 Použití v AiPlayerFacade

Facade místo inline heredoc promptu zavolá PromptLoader:

```php
// Stará verze (inline):
$systemPrompt = <<<PROMPT
Jsi systém pro generování...
$traitsString
...
PROMPT;

// Nová verze (externalizovaná):
$systemPrompt = $this->promptLoader->load('generate_player_traits', [
    'traitKeys' => $traitsString,
]);
```

Facade přijímá `PromptLoader` jako constructor dependency.

### 6.8 Testování PromptLoaderu

```
Soubor: tests/Unit/Domain/Ai/Prompt/PromptLoaderTest.php
Base class: TestCase

Setup:
  - Vytvořit dočasný adresář s testovacími .md šablonami (sys_get_temp_dir())

Testy:
  - testLoadReturnsTemplateContent
    -- .md soubor bez placeholderů
    -- Vrátí obsah souboru

  - testLoadReplacesPlaceholders
    -- .md soubor s {{ name }} a {{ traits }}
    -- Předat variables: ['name' => 'John', 'traits' => '[a, b]']
    -- Ověřit nahrazení

  - testLoadWithEmptyVariablesLeavesNoPlaceholders
    -- .md soubor bez placeholderů, variables = []
    -- Vrátí originální obsah

  - testLoadNonExistentTemplateThrowsException
    -- Neexistující název šablony
    -- PromptTemplateNotFoundException
```

---

## 7. Gemini HTTP request/response formát

### Request body

```json
{
  "systemInstruction": {
    "parts": [
      {"text": "Jsi systém pro generování..."}
    ]
  },
  "contents": [
    {
      "role": "user",
      "parts": [
        {"text": "Popis hráče..."}
      ]
    }
  ],
  "generationConfig": {
    "temperature": 0.6,
    "responseMimeType": "application/json",
    "responseSchema": {
      "type": "object",
      "properties": {
        "traits": {
          "type": "object",
          "properties": {
            "leadership": {"type": "number"},
            "empathy": {"type": "number"}
          }
        },
        "summary": {"type": "string"}
      },
      "required": ["traits", "summary"]
    }
  }
}
```

**Poznámka:** Když `AiRequest.responseSchema` je `null`, `responseMimeType` a `responseSchema` klíče se v `generationConfig` vynechají -- Gemini vrátí volný text.

### Response body (úspěch)

```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {"text": "{\"traits\": {\"leadership\": 0.85}, \"summary\": \"...\"}"}
        ],
        "role": "model"
      },
      "finishReason": "STOP",
      "safetyRatings": [...]
    }
  ],
  "usageMetadata": {
    "promptTokenCount": 150,
    "candidatesTokenCount": 80,
    "totalTokenCount": 230
  },
  "modelVersion": "gemini-2.5-flash"
}
```

### HTTP endpoint

```
POST {baseUrl}/models/{model}:generateContent?key={apiKey}

Headers:
  Content-Type: application/json
```

---

## 7. Error handling strategie

### HTTP úroveň (GeminiClient)

| HTTP status | Akce | Exception |
|---|---|---|
| 200 | Zpracovat odpověď | -- |
| 400 | Bad request (špatný formát) | AiRequestFailedException |
| 403 | Invalid API key | AiRequestFailedException |
| 404 | Model nenalezen | AiRequestFailedException |
| 429 | Rate limit | AiRateLimitExceededException |
| 500, 503 | Server error | AiRequestFailedException |
| Síťová chyba | Connection refused/timeout | AiRequestFailedException |

### Response úroveň (GeminiClient)

| Situace | Akce | Exception |
|---|---|---|
| finishReason = "SAFETY" | Zablokováno | AiResponseBlockedBySafetyException |
| finishReason = "MAX_TOKENS" | Odpověď ořezaná | AiResponseParsingFailedException |
| Chybí candidates | Prázdná odpověď | AiResponseParsingFailedException |
| Nevalidní JSON v candidates | Parsovací chyba | AiResponseParsingFailedException |

### Business úroveň (AiResponseParser)

| Situace | Akce | Exception |
|---|---|---|
| JSON neobsahuje očekávané klíče | Chybějící data | AiResponseParsingFailedException |
| Trait score mimo rozsah 0.0-1.0 | Neplatná hodnota | AiResponseParsingFailedException |
| Neznámý trait key | Neznámý klíč | AiResponseParsingFailedException |

### Exception -> HTTP status mapování (pro Controller)

| Exception | HTTP | Použití |
|---|---|---|
| AiRequestFailedException | 502 | Bad Gateway -- upstream AI selhalo |
| AiRateLimitExceededException | 429 | Too Many Requests -- propagujeme |
| AiResponseBlockedBySafetyException | 422 | Unprocessable -- obsah nevyhovuje |
| AiResponseParsingFailedException | 502 | Bad Gateway -- AI vrátilo neplatná data |

---

## 8. AiLog lifecycle (stavový diagram)

```
  [CREATE]                [HTTP CALL]              [RESULT]
     |                        |                       |
     v                        v                       v
  PENDING  ──── request() ─── PENDING  ──success──> SUCCESS
                                       ──error────> ERROR
```

1. **Facade vytvoří AiLog** se statusem `Pending` a persist (ale NEFLUSH -- transakce)
2. **Facade zavolá GeminiClient**
3. **Při úspěchu:** `$aiLog->recordSuccess($response)` -- status = Success, zapíše response data
4. **Při chybě:** `$aiLog->recordError($message)` -- status = Error, zapíše chybu
5. **Facade zavolá flush** -- vždy, i při chybě (chceme logovat selhání)

Toto zajistí, že **každé** AI volání je zalogováno, včetně selhání.

---

## 9. Metody -- kompletní přehled signatur

### GeminiClient (interface)

```php
public function request(AiRequest $aiRequest): AiResponse;
```

### HttpGeminiClient (implementace)

```php
// Constructor
public function __construct(
    HttpClientInterface $httpClient,
    GeminiConfiguration $configuration,
)

// Public (interface)
public function request(AiRequest $aiRequest): AiResponse;

// Private
/** @param array<string, mixed> $responseData */
private function parseResponse(
    array $responseData,
    int $durationMs,
    string $rawJson,
    string $actionName,
): AiResponse;
```

### AiResponseParser

```php
// Constructor: žádné závislosti

/**
 * @param array<int, TraitDef> $availableTraits
 */
public function parseGenerateTraitsResponse(
    string $content,
    array $availableTraits,
    string $actionName,
): GenerateTraitsResult;

public function parseGenerateSummaryResponse(
    string $content,
    string $actionName,
): GenerateSummaryResult;
```

### PromptLoader

```php
// Constructor
public function __construct(string $templateDirectory)

/**
 * @param array<string, string> $variables
 * @throws PromptTemplateNotFoundException
 */
public function load(string $templateName, array $variables = []): string;
```

### AiPlayerFacade

```php
// Constructor
public function __construct(
    GeminiClient $geminiClient,
    EntityManagerInterface $entityManager,
    AiResponseParser $aiResponseParser,
    PromptLoader $promptLoader,
    GeminiConfiguration $configuration,
)

/**
 * @param array<int, TraitDef> $traits
 */
public function generatePlayerTraitsFromDescription(
    string $description,
    array $traits,
): GenerateTraitsResult;

/**
 * @param array<string, string> $traitStrengths
 */
public function generatePlayerTraitsSummaryDescription(
    array $traitStrengths,
): GenerateSummaryResult;
```

### AiLog

```php
// Constructor
public function __construct(
    string $modelName,
    DateTimeImmutable $createdAt,
    string $actionName,
    string $systemPrompt,
    string $userPrompt,
    string $requestJson,
    float $temperature,
)

// Sémantické metody
public function recordSuccess(AiResponse $response): void;
public function recordError(string $errorMessage, ?int $durationMs = null): void;

// Gettery pro všechny sloupce
public function getId(): Uuid;
public function getCreatedAt(): DateTimeImmutable;
public function getModelName(): string;
public function getActionName(): string;
public function getSystemPrompt(): string;
public function getUserPrompt(): string;
public function getRequestJson(): string;
public function getStatus(): AiLogStatus;
public function getResponseJson(): ?string;
public function getReturnContent(): ?string;
public function getPromptTokenCount(): ?int;
public function getCandidatesTokenCount(): ?int;
public function getTotalTokenCount(): ?int;
public function getDurationMs(): ?int;
public function getModelVersion(): ?string;
public function getFinishReason(): ?string;
public function getTemperature(): float;
public function getErrorMessage(): ?string;
```

---

## 10. Testovací strategie

### 10.1 Unit testy (žádné mock, žádné DB)

#### AiResponseParserTest

```
Soubor: tests/Unit/Domain/Ai/Service/AiResponseParserTest.php
Base class: TestCase

Testy:
  - testParseGenerateTraitsResponseValidJson
    -- Vstup: validní JSON s traits a summary, reálné TraitDef entity
    -- Výstup: GenerateTraitsResult se správnými hodnotami

  - testParseGenerateTraitsResponseMissingTraitsKeyThrowsException
    -- Vstup: JSON bez "traits" klíče
    -- Výstup: AiResponseParsingFailedException

  - testParseGenerateTraitsResponseMissingSummaryKeyThrowsException
    -- Vstup: JSON bez "summary" klíče
    -- Výstup: AiResponseParsingFailedException

  - testParseGenerateTraitsResponseInvalidJsonThrowsException
    -- Vstup: nevalidní JSON string
    -- Výstup: AiResponseParsingFailedException

  - testParseGenerateTraitsResponseUnknownTraitKeyThrowsException
    -- Vstup: JSON s trait key, který neexistuje v availableTraits
    -- Výstup: AiResponseParsingFailedException

  - testParseGenerateTraitsResponseTraitScoreOutOfRangeThrowsException
    -- Vstup: JSON s trait score > 1.0 nebo < 0.0
    -- Výstup: AiResponseParsingFailedException

  - testParseGenerateSummaryResponseValidJson
    -- Vstup: validní JSON se summary
    -- Výstup: GenerateSummaryResult

  - testParseGenerateSummaryResponseMissingSummaryThrowsException
    -- Vstup: JSON bez "summary" klíče
    -- Výstup: AiResponseParsingFailedException
```

#### AiLogTest

```
Soubor: tests/Unit/Domain/Ai/Log/AiLogTest.php
Base class: TestCase

Testy:
  - testConstructorSetsFieldsCorrectly
    -- Ověří všechny constructor parametry + default status = Pending

  - testRecordSuccessUpdatesAllFields
    -- Zavolá recordSuccess s AiResponse
    -- Ověří status = Success, responseJson, returnContent, token counts, durationMs, modelVersion, finishReason

  - testRecordErrorUpdatesStatusAndMessage
    -- Zavolá recordError
    -- Ověří status = Error, errorMessage, durationMs

  - testRecordErrorWithoutDuration
    -- Zavolá recordError bez durationMs
    -- Ověří durationMs zůstává null
```

#### TokenUsageTest

```
Soubor: tests/Unit/Domain/Ai/Result/TokenUsageTest.php
Base class: TestCase

Testy:
  - testConstructorSetsAllCounts
```

#### AiResponseTest

```
Soubor: tests/Unit/Domain/Ai/Result/AiResponseTest.php
Base class: TestCase

Testy:
  - testConstructorSetsAllProperties
```

#### GeminiConfigurationTest

```
Soubor: tests/Unit/Domain/Ai/Client/GeminiConfigurationTest.php
Base class: TestCase

Testy:
  - testGetEndpointUrlBuildsCorrectUrl
    -- Ověří formát: "{baseUrl}/models/{model}:generateContent"

  - testConstructorSetsAllProperties
```

#### AiMessageTest

```
Soubor: tests/Unit/Domain/Ai/Dto/AiMessageTest.php
Base class: TestCase

Testy:
  - testUserFactoryMethodSetsRoleAndContent
  - testModelFactoryMethodSetsRoleAndContent
```

#### AiResponseSchemaTest

```
Soubor: tests/Unit/Domain/Ai/Dto/AiResponseSchemaTest.php
Base class: TestCase

Testy:
  - testToArrayReturnsCorrectStructure
  - testToArrayWithoutDescriptionOmitsIt
```

### 10.2 Unit testy s mock (GeminiClient request building/parsing)

#### GeminiClientTest

```
Soubor: tests/Unit/Domain/Ai/Client/GeminiClientTest.php
Base class: TestCase

Pozn.: Používáme Symfony MockHttpClient (nativní test double,
       ne PHPUnit mock) pro simulaci HTTP odpovědí.

Testy:
  - testRequestSuccessfulResponseReturnsAiResponse
    -- Mock HTTP vrátí validní Gemini response
    -- Ověří AiResponse vlastnosti

  - testRequestWithStructuredOutputIncludesSchemaInBody
    -- Ověří, že request body obsahuje responseMimeType a responseSchema

  - testRequestWithoutSchemaOmitsSchemaFromBody
    -- Ověří, že bez schema request body neobsahuje responseMimeType

  - testRequestWith429ThrowsAiRateLimitExceededException

  - testRequestWith500ThrowsAiRequestFailedException

  - testRequestWithSafetyBlockThrowsAiResponseBlockedBySafetyException
    -- Mock vrátí response s finishReason = "SAFETY"

  - testRequestWithEmptyCandidatesThrowsAiResponseParsingFailedException

  - testRequestNetworkErrorThrowsAiRequestFailedException
    -- Mock vyhodí TransportException

  - testRequestMeasuresDuration
    -- Ověří, že durationMs je non-negative int

  - testRequestUsesCorrectEndpointAndApiKey
    -- Ověří URL a query parametry
```

### 10.3 Integrační testy (s DB, AiClient mockovaný)

#### AiPlayerFacadeTest

```
Soubor: tests/Integration/Domain/Ai/AiPlayerFacadeTest.php
Base class: AbstractIntegrationTestCase

Setup: Přepsat GeminiClient v DI kontejneru za mock
       (nebo použít Symfony test double).

Testy:
  - testGeneratePlayerTraitsFromDescriptionPersistsAiLog
    -- Mock GeminiClient vrátí validní AiResponse s JSON
    -- Ověří, že AiLog je v DB se statusem Success

  - testGeneratePlayerTraitsFromDescriptionReturnsGenerateTraitsResult
    -- Ověří návratový typ a obsah

  - testGeneratePlayerTraitsFromDescriptionLogsTokenUsage
    -- Ověří promptTokenCount, candidatesTokenCount, totalTokenCount v AiLog

  - testGeneratePlayerTraitsFromDescriptionOnErrorPersistsErrorLog
    -- Mock GeminiClient hodí AiRequestFailedException
    -- Ověří, že AiLog je v DB se statusem Error a errorMessage

  - testGeneratePlayerTraitsSummaryDescriptionSuccess
    -- Analogicky pro druhou metodu

  - testGeneratePlayerTraitsSummaryDescriptionOnErrorPersistsErrorLog
```

### 10.4 Funkční testy (HTTP)

#### PlayerController -- existující endpointy

```
Soubor: tests/Functional/Domain/Player/PlayerControllerTest.php

Pozn.: GeminiClient mockovaný v DI kontejneru pro testy.

Testy:
  - testGenerateTraitsReturnsTraitsAndSummary
  - testGenerateTraitsWithoutAuthReturns401
  - testGenerateTraitsWithEmptyDescriptionReturns400
  - testGenerateSummaryDescriptionReturnsJson
  - testGenerateSummaryDescriptionWithoutAuthReturns401
```

### 10.5 Jak mockovat GeminiClient v testech

V integračních a funkčních testech přepíšeme GeminiClient v service containeru:

```php
// V setUp() nebo v config/services_test.yaml:
$mockClient = new class implements GeminiClient {
    public function request(AiRequest $aiRequest): AiResponse
    {
        return new AiResponse(
            content: '{"traits": {"leadership": 0.8}, "summary": "Test."}',
            tokenUsage: new TokenUsage(100, 50, 150),
            durationMs: 200,
            modelVersion: 'gemini-2.5-flash',
            rawResponseJson: '{"candidates": [...]}',
            finishReason: 'STOP',
        );
    }
};

self::getContainer()->set(GeminiClient::class, $mockClient);
```

Alternativně -- dedikovaná třída `FakeGeminiClient` v `tests/`:

```
tests/Fake/FakeGeminiClient.php
```

---

## 11. Migrační plán

### Fáze 1: Příprava (neblokující)

1. Přidat env proměnné do `.env` (GEMINI_API_KEY, GEMINI_MODEL, GEMINI_BASE_URL, GEMINI_DEFAULT_TEMPERATURE)
2. Vytvořit adresářovou strukturu (`Client/`, `Dto/`, `Result/`, `Exceptions/`, `Service/`)
3. Implementovat Value Objects a DTOs: `GeminiConfiguration`, `AiMessage`, `AiResponseSchema`, `AiRequest`, `TokenUsage`, `AiResponse`
4. Implementovat enum `AiLogStatus`
5. Implementovat exceptions (včetně `PromptTemplateNotFoundException`)
5b. Implementovat `PromptLoader` + vytvořit `.md` šablony promptů (`generate_player_traits.md`, `generate_player_summary.md`)

### Fáze 2: Core implementace

6. Implementovat `GeminiClient` (interface) a `HttpGeminiClient` (implementace)
7. Implementovat `AiResponseParser`
8. Přepsat `AiLog` entitu (nová pole, sémantické metody)
9. Vygenerovat Doctrine migraci pro AiLog změny
10. Napsat unit testy pro GeminiClient, AiResponseParser, AiLog, VOs

### Fáze 3: Integrace

11. Přepsat `AiPlayerFacade` (nový GeminiClient místo starého AiClient)
12. Aktualizovat `services.yaml` (interface alias, konfigurace)
13. Napsat integrační testy pro AiPlayerFacade
14. Aktualizovat funkční testy pro PlayerController

### Fáze 4: Cleanup

15. Smazat `AiClient.php` (starý)
16. Smazat prázdné placeholder adresáře (`Gemini/`, `Request/`, `Response/`)
17. Odebrat `google-gemini-php/client` z composer.json (+ `guzzlehttp/guzzle`, `nyholm/psr7`, `psr/http-client` pokud nejsou jinak potřeba)
18. Spustit `composer qa` (PHPCS + PHPStan + testy)

### Fáze 5: Validace

19. Ruční test s reálným Gemini API
20. Ověřit AiLog záznamy v DB (tokeny, status, duration)

---

## 12. Otevřené otázky

1. ~~**Systémové prompty -- kam je přesunout?**~~ **VYŘEŠENO** -- viz sekce 6. Prompty se ukládají jako `.md` soubory v `src/Domain/Ai/Prompt/templates/`, načítá je `PromptLoader` s placeholder substitucí.

2. **Retry logika?**
   Gemini API může občas selhat. Retry s exponential backoff je vhodný kandidát na budoucí rozšíření. Pro teď NE -- first pass je o správné struktuře, retry přidáme jako dekorátor `RetryableGeminiClient implements GeminiClient` (wrapping `HttpGeminiClient`) až bude potřeba.

3. **Streaming?**
   Gemini podporuje streaming přes `streamGenerateContent`. Pro Survivor to zatím není potřeba -- odpovědi jsou krátké. Pokud se objeví use-case (delší generování narativu), bude to nová metoda v interface.

4. **Přesun DTOs z `src/Dto/` do domén?**
   Stávající `GenerateTraitsInput` je v `src/Dto/Game/Player/` -- nesedí do doménové struktury. Doporučuji přesunout do `src/Domain/Player/Dto/GenerateTraitsInput.php`. Ale to je mimo scope tohoto ADR.

---

## 13. Souhrn rozhodnutí

| Rozhodnutí | Důvod |
|---|---|
| Přímé Gemini REST API místo knihovny | Plná kontrola nad usageMetadata a structured output |
| GeminiClient pro abstrakci | Testovatelnost -- mockování v testech |
| AiLog se statusem (pending/success/error) | Logování i selhání; diagnostika |
| Token tracking v AiLog | Monitoring nákladů a usage |
| AiResponseParser jako čistý Service | Testovatelnost bez infrastruktury |
| Strukturovaný výstup přes responseSchema | Eliminace hackového parsování JSON z volného textu |
| Sémantické metody recordSuccess/recordError | Jasný kontrakt místo generického recordResponse |
| Deferred flush (pending -> success/error -> flush) | Atomická operace, i chybový log se zapíše |
| Prompty v .md souborech s placeholder substitucí | Čitelnost, editovatelnost bez PHP znalosti, markdown highlighting |
| Jednoduchý `str_replace` místo Twig | Žádná nová závislost, prompty nepotřebují loops/conditions |
| JSON formát instrukcí pryč z promptů | `responseSchema` to řeší na API úrovni -- prompt je čistší |
