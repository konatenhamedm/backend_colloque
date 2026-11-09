<?php

namespace App\Shared\Application\Service;

use App\Shared\Domain\Entity\Fichier;
use App\Shared\Domain\Repository\FichierRepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Point d'entrée unique pour enregistrer un fichier. Les use cases n'instancient jamais
 * Fichier eux-mêmes : ils passent par ici, ce qui garantit validation + visibilité + dossier.
 */
class FileStorage
{
    private const MIME_JUSTIFICATIF = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    private const MIME_DOCUMENT = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.oasis.opendocument.text'];

    public function __construct(private readonly FichierRepositoryInterface $repository)
    {
    }

    public function storeJustificatif(UploadedFile $file): Fichier
    {
        return $this->store($file, 'justificatifs', self::MIME_JUSTIFICATIF);
    }

    public function storeDocument(UploadedFile $file, string $folder = 'travaux'): Fichier
    {
        return $this->store($file, $folder, self::MIME_DOCUMENT);
    }

    public function storeGenerated(string $bytes, string $name, string $mime, string $folder): Fichier
    {
        $fichier = (new Fichier())->setPath($folder)->setVisibilite(Fichier::PRIVE)->setRawContent($bytes, $name, $mime);
        $this->repository->add($fichier);
        return $fichier;
    }

    /** @param string[] $allowedMimes */
    private function store(UploadedFile $file, string $folder, array $allowedMimes): Fichier
    {
        if (!$file->isValid()) {
            throw new BadRequestHttpException('Le téléversement a échoué, veuillez réessayer.');
        }
        // Le MIME est deviné depuis le contenu (finfo), pas depuis l'en-tête envoyé par le client.
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new BadRequestHttpException('Type de fichier non autorisé.');
        }
        try {
            $fichier = (new Fichier())->setPath($folder)->setVisibilite(Fichier::PRIVE);
            $fichier->setFile($file);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
        $this->repository->add($fichier);
        return $fichier;
    }
}
