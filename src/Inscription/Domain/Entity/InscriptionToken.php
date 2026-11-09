<?php

namespace App\Inscription\Domain\Entity;

use App\Inscription\Infrastructure\Persistence\InscriptionTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton à usage unique envoyé par e-mail : prouve que le participant maîtrise l'adresse
 * avant de créer son compte. Seul le hash SHA-256 est stocké (le jeton en clair n'existe que dans l'e-mail).
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

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(string $email, string $plainToken)
    {
        $this->email = mb_strtolower(trim($email));
        $this->tokenHash = self::hash($plainToken);
        $this->expiresAt = new \DateTimeImmutable('+'.self::TTL_MINUTES.' minutes');
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function getEmail(): string { return $this->email; }
    public function isUsable(): bool { return $this->usedAt === null && $this->expiresAt > new \DateTimeImmutable(); }
    public function markUsed(): void { $this->usedAt = new \DateTimeImmutable(); }
}
