<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Enum\ParticipantRole;
use App\Domain\Player\Player;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_mep_event_player', columns: ['major_event_id', 'player_id'])]
#[ORM\Index(name: 'idx_mep_player', columns: ['player_id'])]
final class MajorEventParticipant
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: MajorEvent::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false)]
    private MajorEvent $majorEvent;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Player $player;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ParticipantRole::class)]
    private ParticipantRole $role;

    public function __construct(MajorEvent $majorEvent, Player $player, ParticipantRole $role)
    {
        $this->id = Uuid::v7();
        $this->majorEvent = $majorEvent;
        $this->player = $player;
        $this->role = $role;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMajorEvent(): MajorEvent
    {
        return $this->majorEvent;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getRole(): ParticipantRole
    {
        return $this->role;
    }

    public function setMajorEvent(MajorEvent $majorEvent): void
    {
        $this->majorEvent = $majorEvent;
    }
}
