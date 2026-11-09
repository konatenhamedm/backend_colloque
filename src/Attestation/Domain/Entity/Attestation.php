<?php

namespace App\Attestation\Domain\Entity;

use App\Attestation\Infrastructure\Persistence\AttestationRepository;
use App\Inscription\Domain\Entity\Inscription;
use App\Shared\Domain\Entity\Fichier;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AttestationRepository::class)]
#[ORM\Table(name: 'attestation')]
#[ORM\HasLifecycleCallbacks]
class Attestation
{
    use CommonFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['attestation:read'])]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Inscription $inscription;

    /** N° lisible imprimé sur le diplôme, ex. PTRCGD-2027-00012 */
    #[ORM\Column(length: 40, unique: true)]
    #[Groups(['attestation:read'])]
    private string $numero = '';

    /** Code aléatoire non devinable, encodé dans le QR code et utilisé par la route publique de vérification. */
    #[ORM\Column(length: 40, unique: true)]
    #[Groups(['attestation:read'])]
    private string $codeVerification = '';

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['attestation:read'])]
    private ?Fichier $fichier = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['attestation:read'])]
    private \DateTimeImmutable $emiseAt;

    public function __construct()
    {
        $this->emiseAt = new \DateTimeImmutable();
        $this->codeVerification = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    }

    public function getId(): ?int { return $this->id; }
    public function getInscription(): Inscription { return $this->inscription; }
    public function setInscription(Inscription $i): self { $this->inscription = $i; return $this; }
    public function getNumero(): string { return $this->numero; }
    public function setNumero(string $n): self { $this->numero = $n; return $this; }
    public function getCodeVerification(): string { return $this->codeVerification; }
    public function getFichier(): ?Fichier { return $this->fichier; }
    public function setFichier(?Fichier $f): self { $this->fichier = $f; return $this; }
    public function getEmiseAt(): \DateTimeImmutable { return $this->emiseAt; }
}
