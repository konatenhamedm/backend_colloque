<?php

namespace App\Colloque\Domain\Entity;

use App\Colloque\Infrastructure\Persistence\CategorieParticipantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/** Catégorie tarifaire : Enseignants-Chercheurs, Étudiants/Doctorants, Autres participants. */
#[ORM\Entity(repositoryClass: CategorieParticipantRepository::class)]
#[ORM\Table(name: 'colloque_categorie_participant')]
class CategorieParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['colloque:read', 'inscription:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'categories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Colloque $colloque = null;

    #[ORM\Column(length: 30)]
    #[Groups(['colloque:read', 'inscription:read'])]
    private string $code = '';

    #[ORM\Column(length: 150)]
    #[Groups(['colloque:read', 'inscription:read'])]
    private string $libelle = '';

    /** Montant en FCFA (entier, pas de décimales). */
    #[ORM\Column]
    #[Groups(['colloque:read', 'inscription:read'])]
    private int $montant = 0;

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
    public function getMontant(): int { return $this->montant; }
    public function setMontant(int $v): self { $this->montant = $v; return $this; }
    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $v): self { $this->ordre = $v; return $this; }
}
