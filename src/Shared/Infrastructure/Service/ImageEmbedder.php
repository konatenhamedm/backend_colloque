<?php

namespace App\Shared\Infrastructure\Service;

use App\Shared\Domain\Entity\Fichier;

/** Intègre une image stockée en data URI dans un PDF (Dompdf n'a pas le droit d'aller chercher des fichiers ou des URL). */
class ImageEmbedder
{
    private const MAX_BYTES = 3 * 1024 * 1024;

    public function dataUri(?Fichier $fichier): ?string
    {
        if ($fichier === null) {
            return null;
        }
        try {
            $path = $fichier->getAbsolutePath();
        } catch (\Throwable) {
            return null;
        }
        if (!is_file($path) || filesize($path) > self::MAX_BYTES) {
            return null;
        }
        $mime = $fichier->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }
        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
