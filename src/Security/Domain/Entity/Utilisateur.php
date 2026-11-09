<?php

namespace App\Security\Domain\Entity;

use App\Security\Infrastructure\Persistence\UtilisateurRepository;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'security_utilisateur')]
#[ORM\HasLifecycleCallbacks]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    use CommonFieldsTrait;

    public const ROLE_PARTICIPANT = 'ROLE_PARTICIPANT';
    public const ROLE_COMITE = 'ROLE_COMITE';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read'])]
    private string $email = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 100)]
    #[Groups(['user:read'])]
    private string $nom = '';

    #[ORM\Column(length: 100)]
    #[Groups(['user:read'])]
    private string $prenom = '';

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $telephone = null;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\Column]
    private bool $isActive = true;

    /** Compte créé à la volée avec un mot de passe temporaire : à changer à la première connexion. */
    #[ORM\Column]
    #[Groups(['user:read'])]
    private bool $mustChangePassword = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = mb_strtolower(trim($email)); return $this; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $hash): self { $this->password = $hash; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = trim($nom); return $this; }
    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): self { $this->prenom = trim($prenom); return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $t): self { $this->telephone = $t; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $a): self { $this->isActive = $a; return $this; }
    public function mustChangePassword(): bool { return $this->mustChangePassword; }
    public function setMustChangePassword(bool $m): self { $this->mustChangePassword = $m; return $this; }
    public function markLogin(): self { $this->lastLoginAt = new \DateTimeImmutable(); return $this; }

    #[Groups(['user:read'])]
    public function getNomComplet(): string { return trim($this->prenom.' '.mb_strtoupper($this->nom)); }

    /** @return string[] */
    public function getRoles(): array { return array_values(array_unique([...$this->roles, self::ROLE_PARTICIPANT])); }
    /** @param string[] $roles */
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }
    public function isStaff(): bool { return in_array(self::ROLE_COMITE, $this->roles, true) || in_array(self::ROLE_ADMIN, $this->roles, true); }
    public function eraseCredentials(): void {}
}
