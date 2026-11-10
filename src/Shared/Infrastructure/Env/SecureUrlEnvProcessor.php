<?php

namespace App\Shared\Infrastructure\Env;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * %env(secure:FRONT_URL)% : force https:// sauf pour localhost / 127.0.0.1 (développement).
 * Évite d'envoyer des liens http:// dans les e-mails si FRONT_URL est resté en http sur le serveur.
 */
final class SecureUrlEnvProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $url = rtrim((string) $getEnv($name), '/');
        $host = (string) parse_url($url, \PHP_URL_HOST);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $url;
        }
        return preg_replace('#^http://#i', 'https://', $url) ?? $url;
    }

    public static function getProvidedTypes(): array
    {
        return ['secure' => 'string'];
    }
}
