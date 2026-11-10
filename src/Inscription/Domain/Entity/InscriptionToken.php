<?php

namespace App\Inscription\Domain\Entity;

use App\Inscription\Infrastructure\Persistence\InscriptionTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton à usage unique envoyé par e-mail : prouve que le participant maîtrise l'adresse
 * avant de créer son compte. Le hash SHA-256 sert à la vérification ; le jeton en clair est conservé
 * uniquement le temps de sa validité (1 h) pour que le back-office puisse retrouver/renvoyer le lien si
 * l'e-mail n'arrive pas. Il est effacé dès l'utilisation et la ligne est purgée à l'expiration.
 */
#[ORM\Entity(repositoryClass: InscriptionTokenRepository::class)]
#[ORM\Table(name: 'inscription_token')]
class InscriptionToken
{
    public const TTL_MINUTES = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $plainToken = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(string $email, string $plainToken)
    {
        $this->email = mb_strtolower(trim($email));
        $this->tokenHash = self::hash($plainToken);
        $this->plainToken = $plainToken;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+'.self::TTL_MINUTES.' minutes');
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function getEmail(): string { return $this->email; }
    public function isUsable(): bool { return $this->usedAt === null && $this->expiresAt > new \DateTimeImmutable(); }
    public function markUsed(): void { $this->usedAt = new \DateTimeImmutable(); $this->plainToken = null; }
    public function getPlainToken(): ?string { return $this->plainToken; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
