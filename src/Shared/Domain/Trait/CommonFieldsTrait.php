<?php

namespace App\Shared\Domain\Trait;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/** Champs communs à toutes les entités : uuid public, horodatage et traçabilité (Gedmo). */
trait CommonFieldsTrait
{
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['common:read'])]
    private ?Uuid $uuid = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'create')]
    #[Groups(['common:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Gedmo\Timestampable(on: 'update')]
    #[Groups(['common:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Gedmo\Blameable(on: 'create')]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Gedmo\Blameable(on: 'update')]
    private ?string $updatedBy = null;

    #[ORM\PrePersist]
    public function initUuid(): void
    {
        $this->uuid ??= Uuid::v4();
    }

    public function getUuid(): ?Uuid { return $this->uuid; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function getUpdatedBy(): ?string { return $this->updatedBy; }
}
