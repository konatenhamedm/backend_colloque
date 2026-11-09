<?php

namespace App\Security\Application\UseCase;

use App\Security\Domain\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordUseCase
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(Utilisateur $user, array $data): void
    {
        $current = (string) ($data['currentPassword'] ?? '');
        $new = (string) ($data['newPassword'] ?? '');

        if (!$this->hasher->isPasswordValid($user, $current)) {
            throw new BadRequestHttpException('Le mot de passe actuel est incorrect.');
        }
        if (strlen($new) < 8) {
            throw new BadRequestHttpException('Le nouveau mot de passe doit contenir au moins 8 caractères.');
        }
        $user->setPassword($this->hasher->hashPassword($user, $new))->setMustChangePassword(false);
        $this->em->flush();
    }
}
