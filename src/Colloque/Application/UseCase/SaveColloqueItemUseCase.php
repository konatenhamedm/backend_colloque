<?php

namespace App\Colloque\Application\UseCase;

use App\Colloque\Domain\Entity\ColloqueItem;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Shared\Application\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Crée ou modifie un animateur / signataire / logo, avec son image (photos et logos publics, signatures privées). */
class SaveColloqueItemUseCase
{
    public function __construct(
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly FileStorage $storage,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(?int $id, array $d, ?UploadedFile $image, bool $removeImage = false): ColloqueItem
    {
        $colloque = $this->colloques->findCurrent() ?? throw new NotFoundHttpException('Aucun colloque actif.');

        if ($id !== null) {
            $item = $this->em->getRepository(ColloqueItem::class)->find($id);
            if ($item === null || $item->getColloque()?->getId() !== $colloque->getId()) {
                throw new NotFoundHttpException('Élément introuvable.');
            }
        } else {
            $type = (string) ($d['type'] ?? '');
            if (!in_array($type, ColloqueItem::TYPES, true)) {
                throw new BadRequestHttpException('Type invalide (INTERVENANT, SIGNATAIRE ou PARTENAIRE).');
            }
            $item = (new ColloqueItem())->setType($type);
            $colloque->addItem($item);
        }

        $nom = trim((string) ($d['nom'] ?? $item->getNom()));
        if ($nom === '') {
            throw new BadRequestHttpException('Le nom est obligatoire.');
        }
        $item->setNom(mb_substr($nom, 0, 255));
        foreach (['titre' => 500, 'role' => 100, 'details' => 1000] as $field => $max) {
            if (array_key_exists($field, $d)) {
                $setter = 'set'.ucfirst($field);
                $item->$setter(mb_substr(trim((string) $d[$field]), 0, $max));
            }
        }
        if (array_key_exists('ordre', $d)) {
            $item->setOrdre((int) $d['ordre']);
        }

        if ($image !== null || $removeImage) {
            $old = $item->getImage();
            $item->setImage(null);
            if ($image !== null) {
                $folder = match ($item->getType()) { ColloqueItem::INTERVENANT => 'intervenants', ColloqueItem::PARTENAIRE => 'logos', default => 'signatures' };
                $item->setImage($this->storage->storeImage($image, $folder, $item->getType() !== ColloqueItem::SIGNATAIRE));
            }
            $this->storage->remove($old);
        }

        $colloque->setSeedKey('custom'); // modifié par un administrateur : le contenu initial n'y touchera plus
        $this->em->flush();
        return $item;
    }
}
