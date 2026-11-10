<?php

namespace App\Colloque\Infrastructure\Seed;

use App\Colloque\Domain\Entity\AxeThematique;
use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Entity\Colloque;
use App\Colloque\Domain\Entity\ColloqueItem;

/**
 * Contenu initial : 3ᵉ Forum des Jeunes Chercheurs en Géographie (FJCG), 26 novembre 2026, UFHB.
 * Aucune image ici (dépôt public) : photos, logos et signatures se téléversent depuis Admin → Paramètres.
 * Partagé par les fixtures de démo et par la commande `app:seed-colloque` (production).
 */
class ColloqueSeeder
{
    /** À incrémenter si ce contenu change : l'ancien contenu non modifié par un admin est alors remplacé (si aucune inscription). */
    public const SEED_KEY = 'fjcg-2026-v1';

    public function build(): Colloque
    {
        $c = (new Colloque())
            ->setTitre('Forum des Jeunes Chercheurs en Géographie (FJCG)')
            ->setSousTitre('3ᵉ édition')
            ->setTheme('De la visibilité scientifique à la valorisation et à l\'innovation de la recherche universitaire')
            ->setDateDebut(new \DateTimeImmutable('2026-11-26'))->setDateFin(new \DateTimeImmutable('2026-11-26'))
            ->setHeures('08h00 – 16h00')
            ->setPrixRepas(2000)
            ->setLieu('Auditorium de l\'UFHB, Abidjan')
            ->setModalite(null)
            ->setCibles(['Étudiants (Master et Thèse)', 'Enseignants-Chercheurs', 'Chercheurs'])
            ->setDateLimitePaiement(new \DateTimeImmutable('2026-11-25'))
            ->setDateLimiteCommunication(new \DateTimeImmutable('2026-11-25'))
            ->setAvantages(['Attestation de participation'])
            ->setCalendrier([
                ['ordre' => 1, 'libelle' => 'Date limite de paiement des frais de participation', 'date' => '25 novembre 2026'],
                ['ordre' => 2, 'libelle' => 'Forum : leçon inaugurale et sessions par axe', 'date' => '26 novembre 2026 · 08h00 – 16h00'],
            ])
            ->setContacts([
                'email' => 'ksanaliou@yahoo.fr',
                'telephones' => ['(+225) 07 77 33 27 20', '(+225) 07 07 90 32 02', '(+225) 07 07 96 31 15'],
                'personnes' => [
                    ['nom' => 'Dr KOUASSI Gilbert', 'telephone' => '(+225) 07 77 33 27 20', 'email' => 'gilbertini2006@yahoo.fr'],
                    ['nom' => 'Dr DIABAGATE Abou', 'telephone' => '(+225) 07 07 90 32 02', 'email' => 'aboudiaba76@yahoo.fr'],
                    ['nom' => 'Dr KAMAGATE Sanaliou', 'telephone' => '(+225) 07 07 96 31 15', 'email' => 'ksanaliou@yahoo.fr'],
                ],
                'beneficiaires' => [], // ancien format (compatibilité avec l'ancien front pendant le déploiement)
                'organisateurs' => ['Université Félix Houphouët-Boigny', 'Institut de Géographie Tropicale · ERESP'],
            ])
            ->setComite([
                ['role' => 'Président du Comité d\'Organisation', 'nom' => 'Dr KAMAGATE Sanaliou', 'details' => 'Maître de Conférences'],
                ['role' => '1er Vice-président', 'nom' => 'Dr KOUASSI Gilbert', 'details' => 'Maître de Conférences'],
                ['role' => '2ème Vice-président', 'nom' => 'Dr (MC) BRENOUM Kouakou David', 'details' => 'UFHB, IGT / ERESP, Abidjan'],
                ['role' => 'Secrétaire général', 'nom' => 'Dr DIABAGATE Abou', 'details' => 'Maître de Conférences'],
                ['role' => 'Secrétaire général adjoint', 'nom' => 'Dre (MC) DIHOUEGBEU Déagai Parfaite', 'details' => 'UFHB, IGT / ERESP'],
                ['role' => 'Trésorière', 'nom' => 'Dr (MC) ADOU Gnangoran Alida Thérèse', 'details' => 'UFHB / IGT, ERESP, Abidjan'],
                ['role' => 'Trésorière adjointe', 'nom' => 'Dre YAO Affoua Marie Rose', 'details' => 'UFHB / IGT, ERESP, Abidjan'],
                ['role' => 'Responsable du protocole et de l\'accueil', 'nom' => 'Dr (MC) KOBENAN Appoh Charlesbor', 'details' => 'UFHB, IGT / ERESP'],
                ['role' => 'Responsable adjoint du protocole et de l\'accueil', 'nom' => 'Dr (MC) WADJA Jean-Bérenger', 'details' => 'UFHB, IGT, ERESP / CAPDEV, Abidjan'],
                ['role' => 'Responsable de la logistique', 'nom' => 'Dr (MC) GOZE Thomas', 'details' => 'UFHB / IGT, ERESP, Abidjan'],
                ['role' => 'Responsable adjoint de la logistique', 'nom' => 'Dre GOORE Lou Binhué Juliana', 'details' => 'UFHB / IGT, ERESP, Abidjan'],
                ['role' => 'Maître de cérémonie', 'nom' => 'Doctorant KACOU Anoh Blaise', 'details' => 'UFHB / IGT, ERESP, Abidjan'],
                ['role' => 'Maître de cérémonie adjoint', 'nom' => 'Doctorant KOFFI Koua Koffi Etienne Trésor', 'details' => 'UFHB / IGT, ERESP, Abidjan'],
                ['role' => 'Responsable de la sécurité', 'nom' => 'Dr EVIAR Ohomon Bernard', 'details' => 'UFHB / IGT, ERESP, Abidjan'],
            ])
            ->setCanauxPaiement([
                ['code' => 'ORANGE_MONEY', 'libelle' => 'Orange Money', 'numeros' => ['07 07 65 69 25', '07 07 66 58 21'],
                    'instructions' => 'Envoyez les frais de participation (10 000 FCFA) à l\'un de ces numéros Orange Money avant le 25 novembre 2026.'],
                ['code' => 'WAVE', 'libelle' => 'Wave', 'numeros' => ['07 07 65 69 25', '01 52 01 25 70'],
                    'instructions' => 'Envoyez les frais de participation (10 000 FCFA) à l\'un de ces numéros Wave avant le 25 novembre 2026.'],
            ])
            ->setDocuments([])
            ->setSeedKey(self::SEED_KEY);

        foreach ([['AXE1', 'Axe 1'], ['AXE2', 'Axe 2'], ['AXE3', 'Axe 3']] as $i => [$code, $lib]) {
            $c->addAxe((new AxeThematique())->setCode($code)->setLibelle($lib.' (intitulé à préciser)')->setOrdre($i + 1));
        }
        foreach ([['ETUDIANT', 'Étudiant (Master / Thèse)'], ['ENSEIGNANT_CHERCHEUR', 'Enseignant-Chercheur'], ['CHERCHEUR', 'Chercheur']] as $i => [$code, $lib]) {
            $c->addCategorie((new CategorieParticipant())->setCode($code)->setLibelle($lib)->setMontant(10000)->setOrdre($i + 1));
        }

        // Animateurs de session (photos : à téléverser depuis Admin → Paramètres → Animateurs)
        foreach ([
            ['Leçon inaugurale', 'Pr Antoine Hauhouot Asseypo', 'Président honoraire de l\'UFHB · Président honoraire de l\'ASCAD'],
            ['Axe 1', 'Pr Jean-Marie Kouakou', 'Directeur de l\'École doctorale Sociétés, Communication, Arts, Lettres et Langues (SCALL) de l\'UFHB'],
            ['Axe 2', 'Dre Annette Ouattara', 'Directrice des Études, du Renforcement des Capacités et des Partenariats au Fonds pour la Science, la Technologie et l\'Innovation (FONSTI)'],
            ['Axe 3', 'Pr Mamoutou Touré', 'Chef du département de géographie – IGT, UFHB'],
        ] as $i => [$role, $nom, $titre]) {
            $c->addItem((new ColloqueItem())->setType(ColloqueItem::INTERVENANT)->setRole($role)->setNom($nom)->setTitre($titre)->setOrdre($i + 1));
        }
        // Signatures du reçu / de l'attestation (image : à téléverser)
        $c->addItem((new ColloqueItem())->setType(ColloqueItem::SIGNATAIRE)->setRole('Le Responsable')->setNom('KAMAGATE Sanaliou')
            ->setTitre('Maître de Conférences, UFHB-IGT')->setDetails('ksanaliou@yahoo.fr')->setOrdre(1));
        $c->addItem((new ColloqueItem())->setType(ColloqueItem::SIGNATAIRE)->setRole('La Trésorière')->setNom('ADOU Gnangoran Alida Thérèse')
            ->setTitre('Maître de Conférences, UFHB / IGT, ERESP')->setDetails('07 07 65 69 25 / 01 52 01 25 70 · agathe_n77@yahoo.fr')->setOrdre(2));
        // Logos (images : à téléverser)
        foreach ([['Université Félix Houphouët-Boigny', 'UFHB'], ['Institut de Géographie Tropicale', 'IGT'], ['Équipe de Recherche Espace, Système et Prospective', 'ERESP']] as $i => [$nom, $role]) {
            $c->addItem((new ColloqueItem())->setType(ColloqueItem::PARTENAIRE)->setNom($nom)->setRole($role)->setOrdre($i + 1));
        }

        return $c;
    }
}
