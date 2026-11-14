<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Repository\PaiementRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** L'administrateur corrige le numéro (Orange Money / Wave) sur lequel un paiement a été déposé. Vide = non précisé. */
class ModifierNumeroDepotUseCase
{
    public function __construct(private readonly PaiementRepositoryInterface $paiements, private readonly EntityManagerInterface $em)
    {
    }

    public function execute(int $paiementId, ?string $numero): Paiement
    {
        $paiement = $this->paiements->findById($paiementId) ?? throw new NotFoundHttpException('Paiement introuvable.');
        $numero = $numero === null ? '' : trim($numero);
        if ($numero === '') {
            $paiement->setNumeroDepot(null);
        } else {
            $colloque = $paiement->getInscription()->getColloque();
            $norm = fn (string $n) => preg_replace('/\D+/', '', $n);
            $retenu = null;
            foreach ($colloque->numerosCanal($paiement->getCanal()->value) as $n) {
                if ($norm($n) === $norm($numero)) {
                    $retenu = $n;
                }
            }
            if ($retenu === null) {
                throw new BadRequestHttpException(sprintf('Ce numéro ne fait pas partie des numéros %s de l\'événement.', $paiement->getCanal()->label()));
            }
            $paiement->setNumeroDepot($retenu);
        }
        $this->em->flush();

        return $paiement;
    }
}
