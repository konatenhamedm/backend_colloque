<?php

namespace App\Inscription\Domain\Entity;

use App\Inscription\Domain\Enum\CanalPaiement;
use App\Inscription\Domain\Enum\StatutPaiement;
use App\Inscription\Infrastructure\Persistence\PaiementRepository;
use App\Shared\Domain\Entity\Fichier;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\Table(name: 'inscription_paiement')]
// Une même référence de transaction ne peut servir qu'une fois par opérateur (anti-fraude).
#[ORM\UniqueConstraint(name: 'uniq_paiement_canal_reference', columns: ['canal', 'reference'])]
#[ORM\HasLifecycleCallbacks]
class Paiement
{
    use CommonFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['paiement:read', 'inscription:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false)]
    private Inscription $inscription;

    #[ORM\Column(length: 30, enumType: CanalPaiement::class)]
    #[Groups(['paiement:read', 'inscription:read'])]
    private CanalPaiement $canal;

    /** Montant attendu, en FCFA : copié depuis la catégorie, jamais saisi par le client. */
    #[ORM\Column]
    #[Groups(['paiement:read', 'inscription:read'])]
    private int $montant = 0;

    /** Référence de transaction : facultative si un reçu est joint (l'un ou l'autre suffit). */
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['paiement:read', 'inscription:read'])]
    private ?string $reference = null;

    /** Numéro Orange Money / Wave sur lequel le participant a déposé (l'événement en propose plusieurs). */
    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['paiement:read', 'inscription:read'])]
    private ?string $numeroDepot = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['paiement:read', 'inscription:read'])]
    private ?Fichier $justificatif = null;

    #[ORM\Column(length: 20, enumType: StatutPaiement::class)]
    #[Groups(['paiement:read', 'inscription:read'])]
    private StatutPaiement $statut = StatutPaiement::EN_ATTENTE;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['paiement:read', 'inscription:read'])]
    private ?string $motifRejet = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['paiement:read'])]
    private ?\DateTimeImmutable $traiteAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['paiement:read'])]
    private ?string $traitePar = null;

    public function getId(): ?int { return $this->id; }
    public function getInscription(): Inscription { return $this->inscription; }
    public function setInscription(Inscription $i): self { $this->inscription = $i; return $this; }
    public function getCanal(): CanalPaiement { return $this->canal; }
    public function setCanal(CanalPaiement $c): self { $this->canal = $c; return $this; }
    public function getMontant(): int { return $this->montant; }
    public function setMontant(int $m): self { $this->montant = $m; return $this; }
    public function getNumeroDepot(): ?string { return $this->numeroDepot; }
    public function setNumeroDepot(?string $n): self { $n = $n === null ? '' : trim($n); $this->numeroDepot = $n === '' ? null : $n; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $r): self { $r = $r === null ? '' : mb_strtoupper(trim($r)); $this->reference = $r === '' ? null : $r; return $this; }
    public function getJustificatif(): ?Fichier { return $this->justificatif; }
    public function setJustificatif(?Fichier $f): self { $this->justificatif = $f; return $this; }
    public function getStatut(): StatutPaiement { return $this->statut; }
    public function getMotifRejet(): ?string { return $this->motifRejet; }
    public function getTraiteAt(): ?\DateTimeImmutable { return $this->traiteAt; }

    #[Groups(['paiement:read', 'inscription:read'])]
    public function getCanalLabel(): string { return $this->canal->label(); }

    #[Groups(['paiement:read'])]
    public function getInscriptionId(): ?int { return $this->inscription->getId(); }

    #[Groups(['paiement:read'])]
    public function getParticipant(): string { return $this->inscription->getUtilisateur()->getNomComplet(); }

    #[Groups(['paiement:read'])]
    public function getParticipantEmail(): string { return $this->inscription->getUtilisateur()->getEmail(); }

    #[Groups(['paiement:read'])]
    public function getCategorieLibelle(): string { return $this->inscription->getCategorie()->getLibelle(); }

    #[Groups(['paiement:read'])]
    public function getInscriptionStatut(): string { return $this->inscription->getStatut()->value; }

    public function valider(string $par): void
    {
        $this->statut = StatutPaiement::VALIDE;
        $this->traiteAt = new \DateTimeImmutable();
        $this->traitePar = $par;
        $this->motifRejet = null;
    }

    public function rejeter(string $motif, string $par): void
    {
        $this->statut = StatutPaiement::REJETE;
        $this->motifRejet = trim($motif);
        $this->traiteAt = new \DateTimeImmutable();
        $this->traitePar = $par;
    }
}
