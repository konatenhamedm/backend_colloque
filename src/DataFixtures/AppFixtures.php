<?php

namespace App\DataFixtures;

use App\Colloque\Domain\Entity\AxeThematique;
use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Entity\Colloque;
use App\Colloque\Infrastructure\Seed\ColloqueSeeder;
use App\Inscription\Domain\Entity\Inscription;
use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Enum\CanalPaiement;
use App\Inscription\Domain\Enum\TypeParticipation;
use App\Security\Domain\Entity\Utilisateur;
use App\Travaux\Domain\Entity\Communication;
use App\Travaux\Domain\Enum\StatutCommunication;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démonstration. Contenu du colloque = flyer officiel (PTRC-GD, Bouaké, 17-19 mars 2027).
 * Comptes : admin@colloque.local / Admin#2027 — comite@colloque.local / Comite#2027 — participants : Participant#2027
 */
class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $em): void
    {
        $colloque = $this->colloque();
        $em->persist($colloque);
        $cats = $colloque->getCategories()->toArray();
        $axes = $colloque->getAxes()->toArray();

        foreach ([
            ['admin@colloque.local', 'Admin#2027', 'Organisateur', 'Admin', Utilisateur::ROLE_ADMIN],
            ['comite@colloque.local', 'Comite#2027', 'Comité', 'Scientifique', Utilisateur::ROLE_COMITE],
        ] as [$email, $pwd, $nom, $prenom, $role]) {
            $u = (new Utilisateur())->setEmail($email)->setNom($nom)->setPrenom($prenom)->setRoles([$role]);
            $em->persist($u->setPassword($this->hasher->hashPassword($u, $pwd)));
        }

        // [nom, prénom, institution, pays, catégorie, type, canal, scénario]
        $demo = [
            ['Koffi', 'Aya', 'Université Félix Houphouët-Boigny', 'Côte d\'Ivoire', 0, 'COMMUNICANT', 'WAVE', 'issued'],
            ['Traoré', 'Mamadou', 'Université Alassane Ouattara', 'Côte d\'Ivoire', 0, 'COMMUNICANT', 'ORANGE_MONEY', 'attended'],
            ['Diallo', 'Fatoumata', 'Université Cheikh Anta Diop', 'Sénégal', 0, 'COMMUNICANT', 'WAVE', 'validated_work'],
            ['Ouédraogo', 'Issa', 'Université Joseph Ki-Zerbo', 'Burkina Faso', 0, 'COMMUNICANT', 'MOOV_MONEY', 'validated'],
            ['Bamba', 'Aïcha', 'Université Internationale ICK', 'Côte d\'Ivoire', 1, 'AUDITEUR', 'ORANGE_MONEY', 'validated'],
            ['N\'Guessan', 'Kévin', 'Université de Bouaké', 'Côte d\'Ivoire', 1, 'AUDITEUR', 'WAVE', 'validated'],
            ['Mba', 'Chantal', 'Université Omar Bongo', 'Gabon', 0, 'COMMUNICANT', 'WESTERN_UNION', 'pending'],
            ['Sow', 'Abdoulaye', 'Université Gaston Berger', 'Sénégal', 0, 'AUDITEUR', 'WAVE', 'pending'],
            ['Yao', 'Prisca', 'INP-HB Yamoussoukro', 'Côte d\'Ivoire', 2, 'AUDITEUR', 'ORANGE_MONEY', 'pending'],
            ['Camara', 'Seydou', 'Consultant indépendant', 'Guinée', 2, 'AUDITEUR', 'MOOV_MONEY', 'pending'],
            ['Tano', 'Esther', 'Université Péléforo Gon Coulibaly', 'Côte d\'Ivoire', 1, 'AUDITEUR', 'WAVE', 'rejected'],
            ['Kouamé', 'Landry', 'Université de Man', 'Côte d\'Ivoire', 1, 'AUDITEUR', 'ORANGE_MONEY', 'validated'],
        ];

        $ref = 1000;
        $emails = [];
        foreach ($demo as $k => [$nom, $prenom, $inst, $pays, $c, $type, $canal, $scenario]) {
            $email = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $prenom.'.'.$nom)).'@exemple.ci';
            $email = preg_replace('/[^a-z0-9.@]/', '', $email);
            $u = (new Utilisateur())->setEmail($email)->setNom($nom)->setPrenom($prenom)->setTelephone('07 0'.random_int(1000000, 9999999))->setRoles([Utilisateur::ROLE_PARTICIPANT]);
            $em->persist($u->setPassword($this->hasher->hashPassword($u, 'Participant#2027')));
            $emails[$email] = $k;

            $ins = (new Inscription())->setUtilisateur($u)->setColloque($colloque)->setCategorie($cats[$c])
                ->setTypeParticipation(TypeParticipation::from($type))->setInstitution($inst)->setPays($pays);
            $p = (new Paiement())->setCanal(CanalPaiement::from($canal))->setReference('TX'.(++$ref).'CI')->setMontant($cats[$c]->getMontant());
            $ins->addPaiement($p);

            if ($scenario === 'rejected') {
                $ins->rejeterPaiement($p, 'Le montant du reçu ne correspond pas aux frais de participation.', 'seed');
            } elseif ($scenario !== 'pending') {
                $ins->validerPaiement($p, 'seed');
            }
            $em->persist($ins);

            if ($type === 'COMMUNICANT' && in_array($scenario, ['issued', 'attended', 'validated_work'], true)) {
                $com = (new Communication())->setInscription($ins)->setAxe($axes[$k % count($axes)])
                    ->setTitre('Gouvernance et dialogue des disciplines : regards croisés sur le développement durable')
                    ->setResumeFr(str_repeat('Résumé de démonstration. ', 50))->setResumeEn(str_repeat('Demo abstract. ', 70))
                    ->setMotsClesFr(['gouvernance', 'développement', 'Afrique', 'interdisciplinarité', 'durabilité'])
                    ->setMotsClesEn(['governance', 'development', 'Africa', 'interdisciplinarity', 'sustainability']);
                $com->evaluer(StatutCommunication::VALIDEE, null);
                $em->persist($com);
            }
            if (in_array($scenario, ['attended', 'issued'], true)) {
                $ins->validerParticipation();
            }
            // « issued » est laissé en ATTENDED : l'attestation (PDF) s'émet depuis le back-office.
        }
        $em->flush();

        // Étale les dates d'inscription pour que la courbe du tableau de bord soit parlante.
        $conn = $em->getConnection();
        foreach (array_keys($emails) as $i => $email) {
            $conn->executeStatement(
                'UPDATE inscription SET created_at = NOW() - (:d || \' days\')::interval WHERE utilisateur_id = (SELECT id FROM security_utilisateur WHERE email = :e)',
                ['d' => ($i * 2) % 20, 'e' => $email]
            );
        }
    }

    private function colloque(): Colloque
    {
        return (new ColloqueSeeder())->build();
    }
}
