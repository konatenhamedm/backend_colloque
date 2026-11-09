<?php

namespace App\Travaux\Application\UseCase;

use App\Colloque\Domain\Repository\AxeThematiqueRepositoryInterface;
use App\Inscription\Application\Service\InscriptionAccessChecker;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Application\Service\FileStorage;
use App\Travaux\Domain\Entity\Communication;
use App\Travaux\Domain\Repository\CommunicationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Dépôt (ou correction) d'une communication. Règles du flyer : résumés de 200 à 300 mots + 5 mots-clés FR et EN. */
class SoumettreCommunicationUseCase
{
    public const MOTS_MIN = 200;
    public const MOTS_MAX = 300;
    public const NB_MOTS_CLES = 5;

    public function __construct(
        private readonly InscriptionAccessChecker $access,
        private readonly AxeThematiqueRepositoryInterface $axes,
        private readonly CommunicationRepositoryInterface $communications,
        private readonly FileStorage $storage,
        private readonly EntityManagerInterface $em,
        private readonly bool $enforceDeadlines,
    ) {
    }

    /** @param int|null $communicationId si fourni : correction d'une communication « À corriger » */
    public function execute(Utilisateur $user, array $data, ?UploadedFile $fichier, ?int $communicationId = null): Communication
    {
        $inscription = $this->access->requireActive($user);
        if (!$inscription->isCommunicant()) {
            throw new AccessDeniedHttpException('Seuls les participants inscrits en tant que communicants peuvent déposer une communication.');
        }
        if ($this->enforceDeadlines && new \DateTimeImmutable('today') > $inscription->getColloque()->getDateLimiteCommunication()) {
            throw new BadRequestHttpException('La date limite d\'envoi des propositions de communication est dépassée.');
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            throw new BadRequestHttpException('Le titre est obligatoire.');
        }
        $axe = $this->axes->findById((int) ($data['axeId'] ?? 0));
        if ($axe === null || $axe->getColloque()->getId() !== $inscription->getColloque()->getId()) {
            throw new BadRequestHttpException('Axe thématique invalide.');
        }
        $resumeFr = $this->resume($data['resumeFr'] ?? '', 'français');
        $resumeEn = $this->resume($data['resumeEn'] ?? '', 'anglais');
        $motsFr = $this->motsCles($data['motsClesFr'] ?? [], 'français');
        $motsEn = $this->motsCles($data['motsClesEn'] ?? [], 'anglais');

        if ($communicationId !== null) {
            $communication = $this->communications->findById($communicationId);
            if ($communication === null || $communication->getInscription()->getId() !== $inscription->getId()) {
                throw new NotFoundHttpException('Communication introuvable.');
            }
            $communication->corriger();
        } else {
            $communication = (new Communication())->setInscription($inscription);
            $this->communications->add($communication);
        }

        $communication->setTitre($titre)->setAxe($axe)->setResumeFr($resumeFr)->setResumeEn($resumeEn)
            ->setMotsClesFr($motsFr)->setMotsClesEn($motsEn);
        if ($fichier !== null) {
            $communication->setFichier($this->storage->storeDocument($fichier, 'travaux'));
        }
        $this->em->flush();

        return $communication;
    }

    private function resume(mixed $texte, string $langue): string
    {
        $texte = trim((string) $texte);
        $n = self::compterMots($texte);
        if ($n < self::MOTS_MIN || $n > self::MOTS_MAX) {
            throw new BadRequestHttpException(sprintf('Le résumé en %s doit contenir entre %d et %d mots (actuellement %d).', $langue, self::MOTS_MIN, self::MOTS_MAX, $n));
        }
        return $texte;
    }

    /** @return string[] */
    private function motsCles(mixed $valeur, string $langue): array
    {
        $liste = is_array($valeur) ? $valeur : preg_split('/[,;\n]/', (string) $valeur);
        $liste = array_values(array_unique(array_filter(array_map('trim', $liste), static fn ($m) => $m !== '')));
        if (count($liste) !== self::NB_MOTS_CLES) {
            throw new BadRequestHttpException(sprintf('Il faut exactement %d mots-clés en %s (reçus : %d).', self::NB_MOTS_CLES, $langue, count($liste)));
        }
        return $liste;
    }

    public static function compterMots(string $texte): int
    {
        return $texte === '' ? 0 : count(preg_split('/\s+/u', trim($texte), -1, PREG_SPLIT_NO_EMPTY));
    }
}
