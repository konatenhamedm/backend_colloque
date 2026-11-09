<?php

namespace App\Security\Application\UseCase;

use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use App\Security\Infrastructure\Security\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginUseCase
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly JwtService $jwt,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array{token: string, user: Utilisateur} */
    public function execute(array $data): array
    {
        $email = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        $user = $email !== '' ? $this->users->findByEmail($email) : null;
        // Même message que le compte existe ou non : pas d'énumération d'adresses.
        if ($user === null || !$user->isActive() || !$this->hasher->isPasswordValid($user, $password)) {
            throw new UnauthorizedHttpException('', 'Email ou mot de passe incorrect.');
        }

        $user->markLogin();
        $this->em->flush();

        return ['token' => $this->jwt->generate(['sub' => $user->getUserIdentifier()]), 'user' => $user];
    }
}
