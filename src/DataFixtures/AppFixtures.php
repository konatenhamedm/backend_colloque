<?php

namespace App\DataFixtures;

use App\Colloque\Domain\Entity\AxeThematique;
use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Entity\Colloque;
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
        $c = (new Colloque())
            ->setTitre('Colloque international du Programme Thématique de Recherche du CAMES — Gouvernance et Développement (PTRC-GD)')
            ->setSousTitre('Appel à communication')
            ->setTheme('Le dialogue entre les sciences au service de la gouvernance et du développement durable de l\'Afrique')
            ->setDateDebut(new \DateTimeImmutable('2027-03-17'))->setDateFin(new \DateTimeImmutable('2027-03-19'))
            ->setLieu('Université Internationale ICK de Bouaké (Côte d\'Ivoire)')
            ->setModalite('En présentiel et en distanciel')
            ->setDateLimiteCommunication(new \DateTimeImmutable('2026-10-10'))
            ->setDateLimitePaiement(new \DateTimeImmutable('2027-01-20'))
            ->setCalendrier([
                ['ordre' => 1, 'libelle' => 'Diffusion de l\'appel', 'date' => '09 août 2026'],
                ['ordre' => 2, 'libelle' => 'Envoi des propositions de communication (résumés et 5 mots-clés en anglais et en français, de 200 à 300 mots)', 'date' => 'Du 09 août au 10 octobre 2026'],
                ['ordre' => 3, 'libelle' => 'Réponses aux auteurs après sélection', 'date' => 'Du 31 octobre au 20 novembre 2026'],
                ['ordre' => 4, 'libelle' => 'Envoi des résumés définitifs pour le livre des résumés du colloque', 'date' => '20 novembre 2026'],
                ['ordre' => 5, 'libelle' => 'Paiement des frais de participation', 'date' => 'Du 20 novembre 2026 au 20 janvier 2027'],
                ['ordre' => 6, 'libelle' => 'Les normes d\'édition et bibliographiques recommandées sont celles du CAMES (NORCAMES)', 'date' => 'Téléchargeables en ligne'],
            ])
            ->setAvantages(['Figuration dans le livre des résumés du colloque', 'Attestation de participation', 'Pauses-café et repas', 'Kit du participant', 'Visite touristique'])
            ->setContacts([
                'email' => 'colloque2ptrgdbke@yahoo.com',
                'telephones' => ['(+225) 0103593436', '(+241) 66082694'],
                'beneficiaires' => [
                    ['nom' => 'KOUASSI Moulo Elysée Landry', 'telephones' => ['07 57 03 03 78', '05 56 97 16 20']],
                    ['nom' => 'ASSAHON Ahou Anne-Naïdée', 'telephones' => ['07 47 37 95 32']],
                ],
                'organisateurs' => ['CAMES', 'Groupe ICK Bouaké'],
            ])
            ->setCanauxPaiement(array_map(fn (CanalPaiement $c) => [
                'code' => $c->value,
                'libelle' => $c->label(),
                'instructions' => 'Effectuez le transfert vers l\'un des bénéficiaires indiqués, puis saisissez la référence de la transaction et joignez la capture ou le reçu. Les frais ne sont pas remboursables.',
            ], [CanalPaiement::WAVE, CanalPaiement::ORANGE_MONEY, CanalPaiement::MOOV_MONEY, CanalPaiement::WESTERN_UNION, CanalPaiement::RIA, CanalPaiement::MONEYGRAM]))
            // PLACEHOLDERS : à remplacer par les 4 intervenants réels (nom, titre, URL de photo).
            ->setIntervenants([
                ['nom' => 'Intervenant 1', 'titre' => 'Fonction / institution', 'photo' => null],
                ['nom' => 'Intervenant 2', 'titre' => 'Fonction / institution', 'photo' => null],
                ['nom' => 'Intervenant 3', 'titre' => 'Fonction / institution', 'photo' => null],
                ['nom' => 'Intervenant 4', 'titre' => 'Fonction / institution', 'photo' => null],
            ])
            ->setDocuments([
                ['titre' => 'Termes de référence (TDR)', 'description' => 'Argumentaire scientifique, axes et modalités de soumission.', 'url' => null],
                ['titre' => 'Normes d\'édition NORCAMES', 'description' => 'Normes d\'édition et bibliographiques recommandées par le CAMES.', 'url' => null],
            ]);

        foreach ([['GU', 'Gouvernance universitaire'], ['GP', 'Gouvernance politique'], ['GSE', 'Gouvernance socioéconomique'],
            ['GH', 'Gouvernance hospitalière'], ['GTE', 'Gouvernance territoriale et environnementale'], ['GG', 'Gouvernance et genre']] as $i => [$code, $lib]) {
            $c->addAxe((new AxeThematique())->setCode($code)->setLibelle($lib)->setOrdre($i + 1));
        }
        foreach ([['ENSEIGNANT_CHERCHEUR', 'Enseignants-Chercheurs et Chercheurs', 40000], ['ETUDIANT_DOCTORANT', 'Étudiants / Doctorants', 15000], ['AUTRE', 'Autres participants', 30000]] as $i => [$code, $lib, $m]) {
            $c->addCategorie((new CategorieParticipant())->setCode($code)->setLibelle($lib)->setMontant($m)->setOrdre($i + 1));
        }
        return $c;
    }
}
