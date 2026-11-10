<?php

namespace App\Colloque\Infrastructure\Seed;

use App\Colloque\Domain\Entity\Colloque;
use App\Colloque\Domain\Entity\ColloqueItem;
use App\Shared\Domain\Entity\Fichier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Corrections de contenu demandées par le client (animateur Axe 2, logos, avantages) après la mise en production, appliquées une seule fois
 * (idempotent) y compris sur un colloque déjà modifié depuis les Paramètres : on ne touche qu'à l'animateur ciblé,
 * et seulement s'il porte encore l'ancien nom (un remplacement fait à la main par un admin est respecté).
 */
class ContentPatches
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /** @return string[] descriptions des changements appliqués (flush à faire par l'appelant) */
    public function appliquer(Colloque $colloque): array
    {
        $faits = [];
        // 10/11/2026 : le Dr Ouattara (Axe 2) est remplacé par le Prof Arnaud Kevin Dayoro.
        foreach ($colloque->getItemsByType(ColloqueItem::INTERVENANT) as $item) {
            if ($item->getRole() !== 'Axe 2' || stripos($item->getNom(), 'Ouattara') === false) {
                continue;
            }
            $item->setNom('Pr Arnaud Kevin Dayoro')->setTitre('Membre du conseil scientifique du FONSTI');
            $path = $this->projectDir.'/assets/seed/animateurs/dayoro.jpg';
            if (is_file($path)) {
                if ($item->getImage() !== null) {
                    $this->em->remove($item->getImage());
                }
                $fichier = (new Fichier())->setPath('intervenants')->setVisibilite(Fichier::PUBLIC)
                    ->setRawContent((string) file_get_contents($path), 'dayoro.jpg', 'image/jpeg');
                $item->setImage($fichier);
                $this->em->persist($fichier);
            }
            $faits[] = 'Axe 2 : Dr Ouattara remplacé par le Pr Arnaud Kevin Dayoro';
        }

        // 10/11/2026 « Correction 1 » : logos UFHB / IGT / ERESP remplacés (repérés par le préfixe « v2- » du nom d'origine).
        foreach ($colloque->getItemsByType(ColloqueItem::PARTENAIRE) as $item) {
            [$file, $mime] = SeedImageImporter::LOGOS[$item->getRole() ?? ''] ?? [null, null];
            $path = $file ? $this->projectDir.'/assets/seed/logos/'.$file : null;
            if ($path === null || !is_file($path) || str_starts_with((string) $item->getImage()?->getOriginalName(), 'v2-')) {
                continue;
            }
            if ($item->getImage() !== null) {
                $this->em->remove($item->getImage());
            }
            $fichier = (new Fichier())->setPath('partenaires')->setVisibilite(Fichier::PUBLIC)
                ->setRawContent((string) file_get_contents($path), 'v2-'.$file, $mime);
            $item->setImage($fichier);
            $this->em->persist($fichier);
            $faits[] = 'Logo '.$item->getRole().' remplacé';
        }

        // « Correction 4 » : avantages remplacés, uniquement s'ils ont encore l'un des contenus initiaux.
        if (in_array($colloque->getAvantages(), [['Déjeuner', 'Diplôme de participation'], ['Attestation de participation']], true)) {
            $colloque->setAvantages(ColloqueSeeder::AVANTAGES);
            $faits[] = 'Avantages de participation mis à jour';
        }
        return $faits;
    }
}
