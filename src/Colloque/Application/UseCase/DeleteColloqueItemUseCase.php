<?php

namespace App\Colloque\Application\UseCase;

use App\Colloque\Domain\Entity\ColloqueItem;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Shared\Application\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteColloqueItemUseCase
{
    public function __construct(
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly FileStorage $storage,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(int $id): void
    {
        $colloque = $this->colloques->findCurrent() ?? throw new NotFoundHttpException('Aucun colloque actif.');
        $item = $this->em->getRepository(ColloqueItem::class)->find($id);
        if ($item === null || $item->getColloque()?->getId() !== $colloque->getId()) {
            throw new NotFoundHttpException('Élément introuvable.');
        }
        $image = $item->getImage();
        $colloque->removeItem($item);
        $this->em->remove($item);
        $this->storage->remove($image);
        $this->em->flush();
    }
}
