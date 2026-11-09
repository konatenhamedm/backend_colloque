<?php

namespace App\Travaux\Domain\Entity;

use App\Colloque\Domain\Entity\AxeThematique;
use App\Inscription\Domain\Entity\Inscription;
use App\Shared\Domain\Entity\Fichier;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use App\Travaux\Domain\Enum\StatutCommunication;
use App\Travaux\Infrastructure\Persistence\CommunicationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/** Proposition de communication : résumé FR/EN de 200 à 300 mots et 5 mots-clés dans chaque langue. */
#[ORM\Entity(repositoryClass: CommunicationRepository::class)]
#[ORM\Table(name: 'travaux_communication')]
#[ORM\HasLifecycleCallbacks]
class Communication
{
    use CommonFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['communication:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Inscription $inscription;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['communication:read'])]
    private AxeThematique $axe;

    #[ORM\Column(length: 300)]
    #[Groups(['communication:read'])]
    private string $titre = '';

    #[ORM\Column(type: 'text')]
    #[Groups(['communication:read'])]
    private string $resumeFr = '';

    #[ORM\Column(type: 'text')]
    #[Groups(['communication:read'])]
    private string $resumeEn = '';

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    #[Groups(['communication:read'])]
    private array $motsClesFr = [];

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    #[Groups(['communication:read'])]
    private array $motsClesEn = [];

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['communication:read'])]
    private ?Fichier $fichier = null;

    #[ORM\Column(length: 20, enumType: StatutCommunication::class)]
    #[Groups(['communication:read'])]
    private StatutCommunication $statut = StatutCommunication::SOUMISE;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['communication:read'])]
    private ?string $commentaireComite = null;

    public function getId(): ?int { return $this->id; }
    public function getInscription(): Inscription { return $this->inscription; }
    public function setInscription(Inscription $i): self { $this->inscription = $i; return $this; }
    public function getAxe(): AxeThematique { return $this->axe; }
    public function setAxe(AxeThematique $a): self { $this->axe = $a; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $t): self { $this->titre = trim($t); return $this; }
    public function getResumeFr(): string { return $this->resumeFr; }
    public function setResumeFr(string $r): self { $this->resumeFr = trim($r); return $this; }
    public function getResumeEn(): string { return $this->resumeEn; }
    public function setResumeEn(string $r): self { $this->resumeEn = trim($r); return $this; }
    public function getMotsClesFr(): array { return $this->motsClesFr; }
    public function setMotsClesFr(array $m): self { $this->motsClesFr = array_values($m); return $this; }
    public function getMotsClesEn(): array { return $this->motsClesEn; }
    public function setMotsClesEn(array $m): self { $this->motsClesEn = array_values($m); return $this; }
    public function getFichier(): ?Fichier { return $this->fichier; }
    public function setFichier(?Fichier $f): self { $this->fichier = $f; return $this; }
    public function getStatut(): StatutCommunication { return $this->statut; }
    public function getCommentaireComite(): ?string { return $this->commentaireComite; }

    #[Groups(['communication:read'])]
    public function getStatutLabel(): string { return $this->statut->label(); }

    #[Groups(['communication:read'])]
    public function getAuteur(): string { return $this->inscription->getUtilisateur()->getNomComplet(); }

    #[Groups(['communication:read'])]
    public function getInstitution(): string { return $this->inscription->getInstitution(); }

    #[Groups(['communication:read'])]
    public function getAuteurEmail(): string { return $this->inscription->getUtilisateur()->getEmail(); }

    public function evaluer(StatutCommunication $decision, ?string $commentaire): void
    {
        if ($decision === StatutCommunication::SOUMISE) {
            throw new \DomainException('Décision invalide.');
        }
        if (in_array($decision, [StatutCommunication::A_CORRIGER, StatutCommunication::REJETEE], true) && trim((string) $commentaire) === '') {
            throw new \DomainException('Un commentaire est obligatoire pour cette décision.');
        }
        $this->statut = $decision;
        $this->commentaireComite = $commentaire !== null ? trim($commentaire) : null;
    }

    /** L'auteur corrige sa proposition : elle repart en évaluation. */
    public function corriger(): void
    {
        if ($this->statut !== StatutCommunication::A_CORRIGER) {
            throw new \DomainException('Cette communication ne peut plus être modifiée.');
        }
        $this->statut = StatutCommunication::SOUMISE;
    }
}
