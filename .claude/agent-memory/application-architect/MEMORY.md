# Application Architect Memory

## Codebase Patterns

### Domain Structure
- Domains under `backend/src/Domain/{DomainName}/`
- Existing domains: Ai, Game, Player, TraitDef, User
- Shared utilities in `backend/src/Shared/` (AbstractApiController)

### Entity Conventions
- All entities use UUID v7 as primary key (via `Symfony\Component\Uid\Uuid`)
- UUID stored as `#[ORM\Column(type: 'uuid')]`
- Entities are final classes with readonly properties where possible
- Constructor creates UUID: `$this->id = Uuid::v7()`
- Collections use Doctrine ArrayCollection
- Relationships configured with `orphanRemoval: true` for cascade deletes

### Route Patterns
- API prefix: `/api/{domain}/{action}`
- Route attribute on method: `#[Route('/api/game/create', name: 'game_create', methods: ['POST'])]`
- Route prefix on class: `#[Route('/api/user', name: 'user_')]` (then individual methods add suffix)
- Controller method names: `{action}Game`, `{action}`, or `{action}Action`

### Authentication
- JWT via LexikJWTAuthenticationBundle
- Controller parameter: `#[CurrentUser] ?User $user`
- Null check in Controller: `if ($user === null) return $this->json(['message' => 'Not authenticated'], 401);`
- No custom middleware — manual null check in each protected endpoint

### Response Conventions
- Success: `$this->json(['data' => ...])` or `$this->json(['message' => '...'])`
- Error: `$this->json(['message' => 'Error message in Czech'], statusCode)`
- Serialization groups: `$this->json($entity, 200, [], ['groups' => 'domain:read'])`
- Messages in Czech (e.g., "Hra vytvořena.", "Hra smazána.")

### Exception Patterns
- Location: `backend/src/Domain/{DomainName}/Exceptions/`
- Naming: `Cannot{Action}{Entity}Because{Reason}Exception extends RuntimeException`
- Example: `CannotDeleteGameBecauseUserIsNotOwnerException`
- Properties: readonly fields for relevant entities/data
- Constructor: accepts entities/data, builds sprintf message, calls parent with message

### DTO Patterns
- No DTOs yet observed in existing endpoints (GameController creates entities directly)
- AbstractApiController provides `getValidatedDto()` helper for DTO validation
- Would follow pattern: `{Action}Input` for requests, `{Entity}Result` for responses

### Serialization Groups
- Naming: `{domain}:read` (e.g., `user:read`, `game:read`)
- Applied to entity properties: `#[Groups(['user:read'])]`
- Used in Controller: `['groups' => 'user:read']`

### Repository Patterns
- Extend ServiceEntityRepository
- Custom finders: `findBy{Criteria}()` (e.g., `findByOwner(User $owner): array`)
- Use QueryBuilder for complex queries

### Existing Relationships
- User → Games (OneToMany, orphanRemoval: true)
- Game → Players (OneToMany, orphanRemoval: true)
- Game → User (ManyToOne, owner)
- Player → PlayerTraits (OneToMany, orphanRemoval: true)
- Player → Game (ManyToOne)

### Controller Inheritance
- Most controllers extend `Symfony\Bundle\FrameworkBundle\Controller\AbstractController`
- TraitDefController extends `App\Shared\Controller\AbstractApiController` (adds DTO validation helper)
- No shared base controller for common auth logic yet

### Facade Patterns
- Constructor injects EntityManagerInterface
- Methods accept entities or IDs, fetch via repository if ID
- Create DateTimeImmutable: `new DateTimeImmutable()`
- End with `$this->entityManager->flush()`
- No service layer yet observed in existing code (Game domain only has Facade)

### Service Layer Usage
- Not yet present in existing Game/User controllers
- Needed when business logic beyond CRUD (ownership checks, state validation)
- Should be created when business rules exist

## Common Issues to Watch

### Cascade Deletes
- Use `orphanRemoval: true` in ORM mappings, NOT `cascade: ['remove']`
- Doctrine handles cascade automatically with orphanRemoval
- Verify cascade works in both directions of bidirectional relationships

### UUID Handling
- Controller route parameters are strings: `deleteGame(string $id, ...)`
- Repository `find()` accepts string, Doctrine converts to UUID
- Entity property is `Uuid` type, not string

### Czech Messages
- All user-facing messages must be in Czech
- Examples: "Hra vytvořena.", "Nemáte oprávnění.", "Nenalezeno."
