<?php

namespace App\Colloque\Infrastructure\Seed;

use App\Colloque\Domain\Entity\AxeThematique;
use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Entity\Colloque;
use App\Inscription\Domain\Enum\CanalPaiement;

/**
 * Contenu initial du colloque (affiche PTRC-GD). Partagé par :
 *  - les fixtures de démo (dev) ;
 *  - la commande `app:seed-colloque` (production : crée le colloque s'il n'existe pas, sans rien effacer).
 */
class ColloqueSeeder
{
    public function build(): Colloque
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
