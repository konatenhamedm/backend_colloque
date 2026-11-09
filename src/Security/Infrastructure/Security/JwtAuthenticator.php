<?php

namespace App\Security\Infrastructure\Security;

use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly UtilisateurRepositoryInterface $users,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $claims = $this->jwt->validate(substr((string) $request->headers->get('Authorization'), 7));
        if ($claims === null || !isset($claims['sub'])) {
            throw new CustomUserMessageAuthenticationException('Session expirée ou jeton invalide.');
        }
        return new SelfValidatingPassport(new UserBadge((string) $claims['sub'], function (string $email) {
            $user = $this->users->findByEmail($email);
            if ($user === null || !$user->isActive()) {
                throw new CustomUserMessageAuthenticationException('Compte introuvable ou désactivé.');
            }
            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->unauthorized($exception->getMessageKey());
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorized('Authentification requise.');
    }

    private function unauthorized(string $message): JsonResponse
    {
        return new JsonResponse(['code' => 401, 'message' => $message, 'errors' => [$message]], 401);
    }
}
