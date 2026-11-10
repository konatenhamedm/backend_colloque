<?php

namespace App\Colloque\Domain\Entity;

use App\Shared\Domain\Entity\Fichier;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Élément configurable du colloque, avec une image optionnelle :
 *  - INTERVENANT : animateur de session (photo publique)         -> landing
 *  - SIGNATAIRE  : personne dont la signature figure sur le reçu / l'attestation (image PRIVÉE)
 *  - PARTENAIRE  : organisme dont le logo figure en en-tête (image publique)
 */
#[ORM\Entity]
#[ORM\Table(name: 'colloque_item')]
#[ORM\HasLifecycleCallbacks]
class ColloqueItem
{
    use CommonFieldsTrait;

    public const INTERVENANT = 'INTERVENANT';
    public const SIGNATAIRE = 'SIGNATAIRE';
    public const PARTENAIRE = 'PARTENAIRE';
    public const TYPES = [self::INTERVENANT, self::SIGNATAIRE, self::PARTENAIRE];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['item:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Colloque $colloque = null;

    #[ORM\Column(length: 20)]
    #[Groups(['item:read'])]
    private string $type = self::INTERVENANT;

    #[ORM\Column(length: 255)]
    #[Groups(['item:read'])]
    private string $nom = '';

    /** Fonction / titre (ex. « Directeur de l'École doctorale… »). */
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['item:read'])]
    private ?string $titre = null;

    /** Rôle dans le programme (ex. « Leçon inaugurale », « Axe 1 ») ou sur le document (ex. « Trésorière »). */
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['item:read'])]
    private ?string $role = null;

    /** Informations complémentaires (contact…). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read'])]
    private ?string $details = null;

    #[ORM\Column]
    #[Groups(['item:read'])]
    private int $ordre = 0;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Fichier $image = null;

    public function getId(): ?int { return $this->id; }
    public function getColloque(): ?Colloque { return $this->colloque; }
    public function setColloque(Colloque $c): self { $this->colloque = $c; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $t): self { $this->type = in_array($t, self::TYPES, true) ? $t : self::INTERVENANT; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $n): self { $this->nom = trim($n); return $this; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(?string $t): self { $this->titre = $t === null || trim($t) === '' ? null : trim($t); return $this; }
    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $r): self { $this->role = $r === null || trim($r) === '' ? null : trim($r); return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $d): self { $this->details = $d === null || trim($d) === '' ? null : trim($d); return $this; }
    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $o): self { $this->ordre = $o; return $this; }
    public function getImage(): ?Fichier { return $this->image; }
    public function setImage(?Fichier $f): self { $this->image = $f; return $this; }

    /** URL publique de l'image (null si absente ou privée, comme les signatures). */
    #[Groups(['item:read'])]
    public function getImageUrl(): ?string { return $this->image?->getWebPath(); }

    /** Identifiant pour lire une image privée via GET /api/fichiers/{uuid} (administrateurs). */
    #[Groups(['item:read'])]
    public function getImageUuid(): ?string { return $this->image?->getUuid()?->toRfc4122(); }
}
