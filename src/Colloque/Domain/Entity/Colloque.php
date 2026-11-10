<?php

namespace App\Colloque\Domain\Entity;

use App\Colloque\Infrastructure\Persistence\ColloqueRepository;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ORM\Entity(repositoryClass: ColloqueRepository::class)]
#[ORM\Table(name: 'colloque')]
#[ORM\HasLifecycleCallbacks]
class Colloque
{
    use CommonFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['colloque:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['colloque:read'])]
    private string $titre = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['colloque:read'])]
    private ?string $sousTitre = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['colloque:read'])]
    private string $theme = '';

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['colloque:read'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['colloque:read'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(length: 255)]
    #[Groups(['colloque:read'])]
    private string $lieu = '';

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['colloque:read'])]
    private ?string $modalite = null;

    /** @var array<int, array{ordre:int,libelle:string,date:string}> */
    #[ORM\Column(type: 'json')]
    #[Groups(['colloque:read'])]
    private array $calendrier = [];

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    #[Groups(['colloque:read'])]
    private array $avantages = [];

    /** @var array<string,mixed> */
    #[ORM\Column(type: 'json')]
    #[Groups(['colloque:read'])]
    private array $contacts = [];

    /** Instructions de paiement par opérateur : {code,libelle,numero,beneficiaire,instructions} */
    #[ORM\Column(type: 'json')]
    #[Groups(['colloque:read'])]
    private array $canauxPaiement = [];

    /** Heures de l'événement, texte libre (ex. « 08h00 – 16h00 »). */
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['colloque:read'])]
    private ?string $heures = null;

    /** Slogan affiché en bas de la page d'accueil et du reçu. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['colloque:read'])]
    private ?string $slogan = null;

    /** Prix du repas facultatif en FCFA ; null ou 0 = pas d'option repas. */
    #[ORM\Column(nullable: true)]
    #[Groups(['colloque:read'])]
    private ?int $prixRepas = null;

    /** Publics visés (ex. « Étudiants (Master et Thèse) »). @var string[] */
    #[ORM\Column(type: 'json')]
    #[Groups(['colloque:read'])]
    private array $cibles = [];

    /** Comité d'organisation : [{role, nom, details}] */
    #[ORM\Column(type: 'json')]
    #[Groups(['colloque:read'])]
    private array $comite = [];

    /** Version du contenu initial (seed). `custom` dès qu'un administrateur modifie la configuration : plus jamais écrasé. */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $seedKey = null;

    /** @var Collection<int, ColloqueItem> */
    #[ORM\OneToMany(targetEntity: ColloqueItem::class, mappedBy: 'colloque', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    /** Documents mis à disposition des inscrits validés : [{titre, description, url}] */
    #[ORM\Column(type: 'json')]
    private array $documents = [];

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['colloque:read'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private \DateTimeImmutable $dateLimiteCommunication;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['colloque:read'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private \DateTimeImmutable $dateLimitePaiement;

    #[ORM\Column]
    #[Groups(['colloque:read'])]
    private bool $isCurrent = true;

    /** @var Collection<int, AxeThematique> */
    #[ORM\OneToMany(targetEntity: AxeThematique::class, mappedBy: 'colloque', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Groups(['colloque:read'])]
    private Collection $axes;

    /** @var Collection<int, CategorieParticipant> */
    #[ORM\OneToMany(targetEntity: CategorieParticipant::class, mappedBy: 'colloque', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Groups(['colloque:read'])]
    private Collection $categories;

    public function __construct()
    {
        $this->axes = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->items = new ArrayCollection();
        $this->dateDebut = new \DateTimeImmutable();
        $this->dateFin = new \DateTimeImmutable();
        $this->dateLimiteCommunication = new \DateTimeImmutable();
        $this->dateLimitePaiement = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $v): self { $this->titre = $v; return $this; }
    public function getSousTitre(): ?string { return $this->sousTitre; }
    public function setSousTitre(?string $v): self { $this->sousTitre = $v; return $this; }
    public function getTheme(): string { return $this->theme; }
    public function setTheme(string $v): self { $this->theme = $v; return $this; }
    public function getDateDebut(): \DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(\DateTimeImmutable $v): self { $this->dateDebut = $v; return $this; }
    public function getDateFin(): \DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(\DateTimeImmutable $v): self { $this->dateFin = $v; return $this; }
    public function getLieu(): string { return $this->lieu; }
    public function setLieu(string $v): self { $this->lieu = $v; return $this; }
    public function getModalite(): ?string { return $this->modalite; }
    public function setModalite(?string $v): self { $this->modalite = $v; return $this; }
    public function getCalendrier(): array { return $this->calendrier; }
    public function setCalendrier(array $v): self { $this->calendrier = $v; return $this; }
    public function getAvantages(): array { return $this->avantages; }
    public function setAvantages(array $v): self { $this->avantages = $v; return $this; }
    public function getContacts(): array { return $this->contacts; }
    public function setContacts(array $v): self { $this->contacts = $v; return $this; }
    public function getCanauxPaiement(): array { return $this->canauxPaiement; }
    public function setCanauxPaiement(array $v): self { $this->canauxPaiement = $v; return $this; }
    public function getSlogan(): ?string { return $this->slogan; }
    public function setSlogan(?string $v): self { $this->slogan = $v; return $this; }
    public function getPrixRepas(): ?int { return $this->prixRepas; }
    public function setPrixRepas(?int $v): self { $this->prixRepas = $v !== null && $v > 0 ? $v : null; return $this; }
    public function getHeures(): ?string { return $this->heures; }
    public function setHeures(?string $v): self { $this->heures = $v; return $this; }
    public function getCibles(): array { return $this->cibles; }
    public function setCibles(array $v): self { $this->cibles = array_values($v); return $this; }
    public function getComite(): array { return $this->comite; }
    public function setComite(array $v): self { $this->comite = array_values($v); return $this; }
    public function getSeedKey(): ?string { return $this->seedKey; }
    public function setSeedKey(?string $v): self { $this->seedKey = $v; return $this; }

    /** @return Collection<int, ColloqueItem> */
    public function getItems(): Collection { return $this->items; }
    public function addItem(ColloqueItem $i): self { if (!$this->items->contains($i)) { $this->items->add($i); $i->setColloque($this); } return $this; }
    public function removeItem(ColloqueItem $i): self { $this->items->removeElement($i); return $this; }
    /** @return ColloqueItem[] */
    public function getItemsByType(string $type): array { return array_values($this->items->filter(fn (ColloqueItem $i) => $i->getType() === $type)->toArray()); }
    public function removeCategorie(CategorieParticipant $c): self { $this->categories->removeElement($c); return $this; }

    /** Animateurs affichés sur la landing (la photo n'est exposée que si elle est publique). @return array<int,array<string,mixed>> */
    #[Groups(['colloque:read'])]
    public function getIntervenants(): array { return $this->publicItems(ColloqueItem::INTERVENANT); }

    /** Logos des organisateurs / partenaires. @return array<int,array<string,mixed>> */
    #[Groups(['colloque:read'])]
    public function getPartenaires(): array { return $this->publicItems(ColloqueItem::PARTENAIRE); }

    private function publicItems(string $type): array
    {
        return array_map(fn (ColloqueItem $i) => ['id' => $i->getId(), 'nom' => $i->getNom(), 'titre' => $i->getTitre(), 'role' => $i->getRole(), 'photo' => $i->getImageUrl()], $this->getItemsByType($type));
    }
    public function getDocuments(): array { return $this->documents; }
    public function setDocuments(array $v): self { $this->documents = $v; return $this; }
    public function getDateLimiteCommunication(): \DateTimeImmutable { return $this->dateLimiteCommunication; }
    public function setDateLimiteCommunication(\DateTimeImmutable $v): self { $this->dateLimiteCommunication = $v; return $this; }
    public function getDateLimitePaiement(): \DateTimeImmutable { return $this->dateLimitePaiement; }
    public function setDateLimitePaiement(\DateTimeImmutable $v): self { $this->dateLimitePaiement = $v; return $this; }
    public function isCurrent(): bool { return $this->isCurrent; }
    public function setIsCurrent(bool $v): self { $this->isCurrent = $v; return $this; }
    /** @return Collection<int, AxeThematique> */
    public function getAxes(): Collection { return $this->axes; }
    /** @return Collection<int, CategorieParticipant> */
    public function getCategories(): Collection { return $this->categories; }

    public function addAxe(AxeThematique $a): self { if (!$this->axes->contains($a)) { $this->axes->add($a); $a->setColloque($this); } return $this; }
    public function addCategorie(CategorieParticipant $c): self { if (!$this->categories->contains($c)) { $this->categories->add($c); $c->setColloque($this); } return $this; }

    /** Sigle de l'événement : le texte entre parenthèses du titre (ex. « FJCG »), utilisé pour nommer les attestations et le logo. */
    #[Groups(['colloque:read'])]
    public function getSigle(): string
    {
        return preg_match('/\(([^)]{2,14})\)/u', $this->titre, $m) ? mb_strtoupper(preg_replace('/[^\p{L}\p{N}]/u', '', $m[1])) : 'FORUM';
    }

    #[Groups(['colloque:read'])]
    public function getAnnee(): int { return (int) $this->dateDebut->format('Y'); }
}
