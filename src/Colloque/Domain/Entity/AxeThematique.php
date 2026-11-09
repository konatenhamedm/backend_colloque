<?php

namespace App\Colloque\Domain\Entity;

use App\Colloque\Infrastructure\Persistence\AxeThematiqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AxeThematiqueRepository::class)]
#[ORM\Table(name: 'colloque_axe')]
class AxeThematique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['colloque:read', 'communication:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'axes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Colloque $colloque = null;

    #[ORM\Column(length: 10)]
    #[Groups(['colloque:read', 'communication:read'])]
    private string $code = '';

    #[ORM\Column(length: 255)]
    #[Groups(['colloque:read', 'communication:read'])]
    private string $libelle = '';

    #[ORM\Column]
    #[Groups(['colloque:read'])]
    private int $ordre = 0;

    public function getId(): ?int { return $this->id; }
    public function getColloque(): ?Colloque { return $this->colloque; }
    public function setColloque(Colloque $c): self { $this->colloque = $c; return $this; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $v): self { $this->code = $v; return $this; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $v): self { $this->libelle = $v; return $this; }
    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $v): self { $this->ordre = $v; return $this; }
}
