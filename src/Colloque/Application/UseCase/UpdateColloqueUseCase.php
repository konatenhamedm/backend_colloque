<?php

namespace App\Colloque\Application\UseCase;

use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Entity\Colloque;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Inscription\Domain\Enum\CanalPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Met à jour la configuration du colloque (une seule requête, tout ou rien).
 * Seuls les champs présents dans le payload sont modifiés.
 */
class UpdateColloqueUseCase
{
    public function __construct(
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(array $d): Colloque
    {
        $c = $this->colloques->findCurrent() ?? throw new NotFoundHttpException('Aucun colloque actif.');

        $this->em->wrapInTransaction(function () use ($c, $d) {
            if (array_key_exists('titre', $d)) { $c->setTitre($this->required($d['titre'], 'Le titre')); }
            if (array_key_exists('sousTitre', $d)) { $c->setSousTitre($this->nullable($d['sousTitre'])); }
            if (array_key_exists('theme', $d)) { $c->setTheme($this->required($d['theme'], 'Le thème')); }
            if (array_key_exists('lieu', $d)) { $c->setLieu($this->required($d['lieu'], 'Le lieu')); }
            if (array_key_exists('heures', $d)) { $c->setHeures($this->nullable($d['heures'])); }
            if (array_key_exists('prixRepas', $d)) {
                $v = $d['prixRepas'];
                if ($v !== null && $v !== '' && (filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false)) {
                    throw new BadRequestHttpException('Le prix du repas doit être un entier en FCFA (0 ou vide = pas de repas).');
                }
                $c->setPrixRepas($v === null || $v === '' ? null : (int) $v);
            }
            if (array_key_exists('modalite', $d)) { $c->setModalite($this->nullable($d['modalite'])); }
            if (array_key_exists('dateDebut', $d)) { $c->setDateDebut($this->date($d['dateDebut'], 'La date de début')); }
            if (array_key_exists('dateFin', $d)) { $c->setDateFin($this->date($d['dateFin'], 'La date de fin')); }
            if (array_key_exists('dateLimitePaiement', $d)) { $c->setDateLimitePaiement($this->date($d['dateLimitePaiement'], 'La date limite de paiement')); }
            if ($c->getDateFin() < $c->getDateDebut()) {
                throw new BadRequestHttpException('La date de fin ne peut pas précéder la date de début.');
            }
            if (array_key_exists('cibles', $d)) { $c->setCibles($this->lines($d['cibles'])); }
            if (array_key_exists('avantages', $d)) { $c->setAvantages($this->lines($d['avantages'])); }
            if (array_key_exists('calendrier', $d)) { $c->setCalendrier($this->calendrier($d['calendrier'])); }
            if (array_key_exists('contacts', $d)) { $c->setContacts($this->contacts($d['contacts'], $c->getContacts())); }
            if (array_key_exists('comite', $d)) { $c->setComite($this->comite($d['comite'])); }
            if (array_key_exists('canauxPaiement', $d)) { $c->setCanauxPaiement($this->canaux($d['canauxPaiement'])); }
            if (array_key_exists('categories', $d)) { $this->categories($c, $d['categories']); }

            $c->setSeedKey('custom'); // modifié par un administrateur : ne sera plus jamais remplacé par un contenu initial
        });

        return $c;
    }

    private function required(mixed $v, string $label): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            throw new BadRequestHttpException($label.' est obligatoire.');
        }
        return mb_substr($v, 0, 1000);
    }

    private function nullable(mixed $v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : mb_substr($v, 0, 255);
    }

    private function date(mixed $v, string $label): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $v, 0, 10));
        if ($d === false) {
            throw new BadRequestHttpException($label.' est invalide (format AAAA-MM-JJ).');
        }
        return $d;
    }

    /** @return string[] une valeur par ligne (tableau ou texte multi-lignes) */
    private function lines(mixed $v): array
    {
        $list = is_array($v) ? $v : preg_split('/\R/', (string) $v);
        return array_slice(array_values(array_filter(array_map(fn ($x) => mb_substr(trim((string) $x), 0, 300), $list), fn ($x) => $x !== '')), 0, 30);
    }

    private function rows(mixed $v, int $max = 60): array
    {
        if (!is_array($v)) {
            throw new BadRequestHttpException('Format de liste invalide.');
        }
        return array_slice(array_values(array_filter($v, 'is_array')), 0, $max);
    }

    private function calendrier(mixed $v): array
    {
        $out = [];
        foreach ($this->rows($v) as $r) {
            $lib = trim((string) ($r['libelle'] ?? ''));
            if ($lib !== '') {
                $out[] = ['ordre' => count($out) + 1, 'libelle' => mb_substr($lib, 0, 300), 'date' => mb_substr(trim((string) ($r['date'] ?? '')), 0, 150)];
            }
        }
        return $out;
    }

    private function contacts(mixed $v, array $current): array
    {
        if (!is_array($v)) {
            throw new BadRequestHttpException('Contacts invalides.');
        }
        $email = trim((string) ($v['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('L\'adresse e-mail de contact est invalide.');
        }
        $personnes = [];
        foreach ($this->rows($v['personnes'] ?? []) as $p) {
            $nom = trim((string) ($p['nom'] ?? ''));
            if ($nom !== '') {
                $personnes[] = ['nom' => mb_substr($nom, 0, 200), 'telephone' => mb_substr(trim((string) ($p['telephone'] ?? '')), 0, 60), 'email' => mb_substr(trim((string) ($p['email'] ?? '')), 0, 180)];
            }
        }
        return [
            'email' => $email,
            'telephones' => $this->lines($v['telephones'] ?? []),
            'personnes' => $personnes,
            'beneficiaires' => $current['beneficiaires'] ?? [],
            'organisateurs' => $this->lines($v['organisateurs'] ?? []),
        ];
    }

    private function comite(mixed $v): array
    {
        $out = [];
        foreach ($this->rows($v, 100) as $r) {
            $nom = trim((string) ($r['nom'] ?? ''));
            if ($nom !== '') {
                $out[] = ['role' => mb_substr(trim((string) ($r['role'] ?? '')), 0, 200), 'nom' => mb_substr($nom, 0, 200), 'details' => mb_substr(trim((string) ($r['details'] ?? '')), 0, 300)];
            }
        }
        return $out;
    }

    private function canaux(mixed $v): array
    {
        $out = [];
        $seen = [];
        foreach ($this->rows($v, 12) as $r) {
            $canal = CanalPaiement::tryFrom((string) ($r['code'] ?? '')) ?? throw new BadRequestHttpException('Moyen de paiement inconnu.');
            if (isset($seen[$canal->value])) {
                throw new BadRequestHttpException('Le moyen de paiement « '.$canal->label().' » est présent deux fois.');
            }
            $seen[$canal->value] = true;
            $out[] = [
                'code' => $canal->value,
                'libelle' => $canal->label(),
                'numeros' => $this->lines($r['numeros'] ?? []),
                'instructions' => mb_substr(trim((string) ($r['instructions'] ?? '')), 0, 600),
            ];
        }
        return $out;
    }

    /** Synchronise les catégories : mise à jour par id, création sans id, suppression des absentes (refusée si des inscrits les utilisent). */
    private function categories(Colloque $c, mixed $v): void
    {
        $rows = $this->rows($v, 20);
        if ($rows === []) {
            throw new BadRequestHttpException('Il faut au moins une catégorie tarifaire.');
        }
        $existing = [];
        foreach ($c->getCategories() as $cat) {
            $existing[$cat->getId()] = $cat;
        }
        $keep = [];
        foreach ($rows as $i => $r) {
            $libelle = $this->required($r['libelle'] ?? '', 'Le libellé de la catégorie');
            $montant = filter_var($r['montant'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000]]);
            if ($montant === false) {
                throw new BadRequestHttpException('Le montant de « '.$libelle.' » est invalide (entier en FCFA).');
            }
            $cat = isset($r['id'], $existing[(int) $r['id']]) ? $existing[(int) $r['id']] : null;
            if ($cat === null) {
                $cat = (new CategorieParticipant())->setCode(strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $libelle) ?: $libelle), 0, 20)).'_'.bin2hex(random_bytes(2)));
                $c->addCategorie($cat);
            }
            $cat->setLibelle($libelle)->setMontant($montant)->setOrdre($i + 1);
            $keep[spl_object_id($cat)] = true;
        }
        foreach ($existing as $cat) {
            if (isset($keep[spl_object_id($cat)])) {
                continue;
            }
            $used = (int) $this->em->createQuery('SELECT COUNT(i.id) FROM App\Inscription\Domain\Entity\Inscription i WHERE i.categorie = :c')->setParameter('c', $cat)->getSingleScalarResult();
            if ($used > 0) {
                throw new BadRequestHttpException('La catégorie « '.$cat->getLibelle().' » ne peut pas être supprimée : '.$used.' inscription(s) l\'utilisent.');
            }
            $c->removeCategorie($cat);
            $this->em->remove($cat);
        }
    }
}
