<?php

namespace App\Inscription\Application\UseCase;

use App\Colloque\Domain\Repository\CategorieParticipantRepositoryInterface;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Inscription\Application\Service\InscriptionNotifierInterface;
use App\Inscription\Domain\Entity\Inscription;
use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Enum\CanalPaiement;
use App\Inscription\Domain\Enum\TypeParticipation;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Inscription\Domain\Repository\InscriptionTokenRepositoryInterface;
use App\Inscription\Domain\Repository\PaiementRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use App\Shared\Application\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Étape 2 (après vérification de l'e-mail par jeton) : crée en une seule transaction le compte (mot de passe choisi),
 * l'inscription (PENDING_PAYMENT) et le paiement à vérifier avec son justificatif.
 */
class SoumettreInscriptionUseCase
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $users,
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly CategorieParticipantRepositoryInterface $categories,
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly PaiementRepositoryInterface $paiements,
        private readonly FileStorage $storage,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly InscriptionNotifierInterface $notifier,
        private readonly InscriptionTokenRepositoryInterface $tokens,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array{inscription: Inscription, utilisateur: Utilisateur} */
    public function execute(array $data, ?UploadedFile $justificatif): array
    {
        $token = $this->tokens->findByPlainToken((string) ($data['token'] ?? ''));
        if ($token === null || !$token->isUsable()) {
            throw new GoneHttpException('Ce lien est invalide ou a expiré. Recommencez votre inscription pour recevoir un nouveau lien.');
        }
        $data['email'] = $token->getEmail(); // l'e-mail vient du jeton, jamais du formulaire
        if (strlen((string) ($data['password'] ?? '')) < 8) {
            throw new BadRequestHttpException('Le mot de passe doit contenir au moins 8 caractères.');
        }
        foreach (['nom', 'prenom', 'institution', 'categorieId', 'canal'] as $required) {
            if (trim((string) ($data[$required] ?? '')) === '') {
                throw new BadRequestHttpException(sprintf('Le champ « %s » est obligatoire.', $required));
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Adresse e-mail invalide.');
        }
        // Preuve de paiement : la référence OU le reçu suffisent (l'admin vérifie ensuite sur son relevé).
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '' && $justificatif === null) {
            throw new BadRequestHttpException('Indiquez la référence de la transaction ou joignez le reçu de paiement.');
        }
        $canal = CanalPaiement::tryFrom((string) $data['canal']) ?? throw new BadRequestHttpException('Canal de paiement inconnu.');
        $type = TypeParticipation::tryFrom((string) ($data['typeParticipation'] ?? 'AUDITEUR')) ?? throw new BadRequestHttpException('Type de participation invalide.');

        $colloque = $this->colloques->findCurrent() ?? throw new BadRequestHttpException('Les inscriptions ne sont pas ouvertes.');
        $categorie = $this->categories->findById((int) $data['categorieId']);
        if ($categorie === null || $categorie->getColloque()->getId() !== $colloque->getId()) {
            throw new BadRequestHttpException('Catégorie de participant invalide.');
        }
        $acceptes = array_column($colloque->getCanauxPaiement(), 'code');
        if ($acceptes !== [] && !in_array($canal->value, $acceptes, true)) {
            throw new BadRequestHttpException('Ce mode de paiement n\'est pas accepté pour cet événement.');
        }
        if ($this->users->findByEmail($data['email']) !== null) {
            throw new ConflictHttpException('Un compte existe déjà avec cette adresse e-mail. Connectez-vous pour suivre votre inscription.');
        }
        if ($reference !== '' && $this->paiements->existsReference($canal, $reference)) {
            throw new ConflictHttpException('Cette référence de transaction a déjà été utilisée.');
        }

        $user = (new Utilisateur())
            ->setEmail($data['email'])->setNom($data['nom'])->setPrenom($data['prenom'])
            ->setTelephone($data['telephone'] ?? null)
            ->setRoles([Utilisateur::ROLE_PARTICIPANT]);
        $user->setPassword($this->hasher->hashPassword($user, (string) $data['password']));

        $inscription = (new Inscription())
            ->setUtilisateur($user)->setColloque($colloque)->setCategorie($categorie)
            ->setTypeParticipation($type)->setInstitution($data['institution'])->setPays($data['pays'] ?? null)
            ->setRepas(filter_var($data['repas'] ?? false, FILTER_VALIDATE_BOOLEAN));

        $paiement = (new Paiement())
            ->setCanal($canal)->setReference($reference)
            ->setMontant($inscription->getMontantTotal()) // le montant vient de la catégorie (+ repas), pas du client
            ->setJustificatif($justificatif !== null ? $this->storage->storeJustificatif($justificatif) : null);
        $inscription->addPaiement($paiement);

        $this->users->add($user);
        $this->inscriptions->add($inscription);
        $token->markUsed();
        $this->em->flush();

        $this->notifier->inscriptionRecue($inscription);
        $this->notifier->nouvelleInscription($inscription);

        return ['inscription' => $inscription, 'utilisateur' => $user];
    }
}
