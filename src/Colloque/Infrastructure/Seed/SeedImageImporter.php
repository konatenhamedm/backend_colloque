<?php

namespace App\Colloque\Infrastructure\Seed;

use App\Colloque\Domain\Entity\Colloque;
use App\Colloque\Domain\Entity\ColloqueItem;
use App\Shared\Domain\Entity\Fichier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Photos initiales des animateurs (assets/seed/animateurs/), copiées dans le stockage public au déploiement.
 * Ne remplace JAMAIS une image déjà présente (téléversée par un admin) : seuls les animateurs sans photo sont complétés.
 */
class SeedImageImporter
{
    /** rôle de l'animateur -> fichier */
    public const PHOTOS = ['Leçon inaugurale' => 'hauhouot.jpg', 'Axe 1' => 'kouakou.jpg', 'Axe 2' => 'dayoro.jpg', 'Axe 3' => 'toure.jpg'];

    /** rôle du partenaire -> [fichier, mime] ; préfixe « v2- » = logos corrigés du 10/11/2026 */
    public const LOGOS = ['UFHB' => ['ufhb.jpg', 'image/jpeg'], 'IGT' => ['igt.jpg', 'image/jpeg'], 'ERESP' => ['eresp.png', 'image/png']];

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /** @return int nombre de photos importées (flush à faire par l'appelant) */
    public function importPhotos(Colloque $colloque): int
    {
        $n = 0;
        foreach ($colloque->getItemsByType(ColloqueItem::INTERVENANT) as $item) {
            $file = self::PHOTOS[$item->getRole() ?? ''] ?? null;
            $path = $file ? $this->projectDir.'/assets/seed/animateurs/'.$file : null;
            if ($item->getImage() !== null || $path === null || !is_file($path)) {
                continue;
            }
            $fichier = (new Fichier())->setPath('intervenants')->setVisibilite(Fichier::PUBLIC)
                ->setRawContent((string) file_get_contents($path), $file, 'image/jpeg');
            $item->setImage($fichier);
            $this->em->persist($fichier);
            ++$n;
        }
        foreach ($colloque->getItemsByType(ColloqueItem::PARTENAIRE) as $item) {
            [$file, $mime] = self::LOGOS[$item->getRole() ?? ''] ?? [null, null];
            $path = $file ? $this->projectDir.'/assets/seed/logos/'.$file : null;
            if ($item->getImage() !== null || $path === null || !is_file($path)) {
                continue;
            }
            $fichier = (new Fichier())->setPath('partenaires')->setVisibilite(Fichier::PUBLIC)
                ->setRawContent((string) file_get_contents($path), 'v2-'.$file, $mime);
            $item->setImage($fichier);
            $this->em->persist($fichier);
            ++$n;
        }
        return $n;
    }
}
