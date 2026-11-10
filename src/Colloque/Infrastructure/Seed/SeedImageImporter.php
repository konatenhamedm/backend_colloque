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
    public const PHOTOS = ['Leçon inaugurale' => 'hauhouot.jpg', 'Axe 1' => 'kouakou.jpg', 'Axe 2' => 'ouattara.jpg', 'Axe 3' => 'toure.jpg'];

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
        return $n;
    }
}
