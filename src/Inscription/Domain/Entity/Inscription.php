<?php

namespace App\Inscription\Domain\Entity;

use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Entity\Colloque;
use App\Inscription\Domain\Enum\StatutInscription;
use App\Inscription\Domain\Enum\StatutPaiement;
use App\Inscription\Domain\Enum\TypeParticipation;
use App\Inscription\Infrastructure\Persistence\InscriptionRepository;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
#[ORM\Table(name: 'inscription')]
#[ORM\UniqueConstraint(name: 'uniq_inscription_user_colloque', columns: ['utilisateur_id', 'colloque_id'])]
#[ORM\Index(name: 'idx_inscription_statut', columns: ['statut'])]
#[ORM\HasLifecycleCallbacks]
class Inscription
{
    use CommonFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['inscription:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inscription:read'])]
    private Utilisateur $utilisateur;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Colloque $colloque;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inscription:read'])]
    private CategorieParticipant $categorie;

    #[ORM\Column(length: 20, enumType: TypeParticipation::class)]
    #[Groups(['inscription:read'])]
    private TypeParticipation $typeParticipation = TypeParticipation::AUDITEUR;

    #[ORM\Column(length: 255)]
    #[Groups(['inscription:read'])]
    private string $institution = '';

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['inscription:read'])]
    private ?string $pays = null;

    #[ORM\Column(length: 30, enumType: StatutInscription::class)]
    #[Groups(['inscription:read'])]
    private StatutInscription $statut = StatutInscription::PENDING_PAYMENT;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['inscription:read'])]
    private ?string $motifRejet = null;

    #[ORM\Column]
    #[Groups(['inscription:read'])]
    private bool $presence = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['inscription:read'])]
    private ?\DateTimeImmutable $valideAt = null;

    /** @var Collection<int, Paiement> */
    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'inscription', cascade: ['persist'])]
    #[ORM\OrderBy(['id' => 'DESC'])]
    #[Groups(['inscription:read'])]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getColloque(): Colloque { return $this->colloque; }
    public function setColloque(Colloque $c): self { $this->colloque = $c; return $this; }
    public function getCategorie(): CategorieParticipant { return $this->categorie; }
    public function setCategorie(CategorieParticipant $c): self { $this->categorie = $c; return $this; }
    public function getTypeParticipation(): TypeParticipation { return $this->typeParticipation; }
    public function setTypeParticipation(TypeParticipation $t): self { $this->typeParticipation = $t; return $this; }
    public function getInstitution(): string { return $this->institution; }
    public function setInstitution(string $i): self { $this->institution = trim($i); return $this; }
    public function getPays(): ?string { return $this->pays; }
    public function setPays(?string $p): self { $this->pays = $p; return $this; }
    public function getStatut(): StatutInscription { return $this->statut; }
    public function getMotifRejet(): ?string { return $this->motifRejet; }
    public function hasPresence(): bool { return $this->presence; }
    public function getValideAt(): ?\DateTimeImmutable { return $this->valideAt; }
    /** @return Collection<int, Paiement> */
    public function getPaiements(): Collection { return $this->paiements; }
    public function isCommunicant(): bool { return $this->typeParticipation === TypeParticipation::COMMUNICANT; }

    #[Groups(['inscription:read'])]
    public function getStatutLabel(): string { return $this->statut->label(); }

    #[Groups(['inscription:read'])]
    public function getDernierPaiement(): ?Paiement { return $this->paiements->first() ?: null; }

    /** Seule porte de sortie pour changer de statut : la machine à états est vérifiée ici. */
    public function transitionTo(StatutInscription $target): void
    {
        if (!$this->statut->canTransitionTo($target)) {
            throw new \DomainException(sprintf('Transition impossible : « %s » → « %s ».', $this->statut->label(), $target->label()));
        }
        $this->statut = $target;
    }

    public function addPaiement(Paiement $p): void
    {
        $p->setInscription($this);
        $this->paiements->add($p);
    }

    public function validerPaiement(Paiement $paiement, string $par): void
    {
        if ($paiement->getStatut() !== StatutPaiement::EN_ATTENTE) {
            throw new \DomainException('Ce paiement a déjà été traité.');
        }
        $this->transitionTo(StatutInscription::PAYMENT_VALIDATED);
        $paiement->valider($par);
        $this->motifRejet = null;
        $this->valideAt = new \DateTimeImmutable();
    }

    public function rejeterPaiement(Paiement $paiement, string $motif, string $par): void
    {
        if ($paiement->getStatut() !== StatutPaiement::EN_ATTENTE) {
            throw new \DomainException('Ce paiement a déjà été traité.');
        }
        if (trim($motif) === '') {
            throw new \DomainException('Un motif de rejet est obligatoire.');
        }
        $this->transitionTo(StatutInscription::REJECTED);
        $paiement->rejeter($motif, $par);
        $this->motifRejet = trim($motif);
    }

    /** Le participant renvoie une nouvelle preuve après un rejet. */
    public function soumettreNouveauPaiement(Paiement $paiement): void
    {
        $this->transitionTo(StatutInscription::PENDING_PAYMENT);
        $this->addPaiement($paiement);
    }

    public function validerParticipation(): void
    {
        $this->presence = true;
        $this->transitionTo(StatutInscription::ATTENDED_AND_VALIDATED);
    }

    public function marquerAttestationEmise(): void
    {
        $this->transitionTo(StatutInscription::CERTIFICATE_ISSUED);
    }
}
