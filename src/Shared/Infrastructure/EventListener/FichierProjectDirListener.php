<?php

namespace App\Shared\Infrastructure\EventListener;

use App\Shared\Domain\Entity\Fichier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fichier ne connaît pas le kernel : ce listener lui injecte les répertoires de stockage
 * pour qu'aucun appelant ne puisse l'oublier (même idée que dans Educ360).
 * Appliqué à l'écriture (prePersist, avant les callbacks de l'entité) et à la lecture (postLoad).
 */
#[AsDoctrineListener(event: Events::prePersist, priority: 100)]
#[AsDoctrineListener(event: Events::postLoad)]
class FichierProjectDirListener
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%private_dir%')] private readonly string $privateDir,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->apply($args->getObject());
    }

    /** Un Fichier relu depuis la base doit lui aussi savoir où se trouve son contenu. */
    public function postLoad(PostLoadEventArgs $args): void
    {
        $this->apply($args->getObject());
    }

    private function apply(object $entity): void
    {
        if ($entity instanceof Fichier) {
            $entity->setDirs($this->projectDir, $this->privateDir);
        }
    }
}
