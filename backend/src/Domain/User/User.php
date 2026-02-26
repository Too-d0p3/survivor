<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Game\Game;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "app_user")]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['user:read'])]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read'])]
    private string $email;

    /** @var array<string> */
    #[ORM\Column]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    /** @var Collection<int, Game> */
    #[ORM\OneToMany(targetEntity: Game::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $games;

    public function __construct(
        string $email,
    ) {
        $this->id = Uuid::v7();
        $this->email = $email;
        $this->games = new ArrayCollection();
    }

    public function eraseCredentials(): void
    {
    }

    public function addGame(Game $game): void
    {
        if ($this->games->contains($game)) {
            return;
        }

        $this->games->add($game);
    }

    public function removeGame(Game $game): void
    {
        $this->games->removeElement($game);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        assert($this->email !== '');

        return $this->email;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return array<int, Game>
     */
    public function getGames(): array
    {
        return $this->games->toArray();
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
}
