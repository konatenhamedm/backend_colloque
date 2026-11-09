<?php

namespace App\Shared\Domain\Entity;

use App\Shared\Domain\Trait\CommonFieldsTrait;
use App\Shared\Infrastructure\Repository\FichierRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Fichier téléversé. Même mécanique que le reste de tes projets : l'entité porte son cycle
 * de vie (prePersist -> nom unique, postPersist -> écriture disque, postRemove -> nettoyage).
 *
 * Nouveauté ici : une `visibilite`.
 *  - PUBLIC : public/uploads/<path>/<nom>   (logos, visuels)
 *  - PRIVE  : var/data/<path>/<nom>         (justificatifs de paiement, attestations) — jamais servi
 *    directement : il passe par FichierController qui contrôle les droits.
 */
#[ORM\Entity(repositoryClass: FichierRepository::class)]
#[ORM\Table(name: 'param_fichier')]
#[ORM\HasLifecycleCallbacks]
class Fichier
{
    use CommonFieldsTrait;

    public const PUBLIC = 'PUBLIC';
    public const PRIVE = 'PRIVE';

    /** Extensions refusées quoi qu'il arrive (un script déposé sous son vrai nom serait exécutable). */
    private const EXTENSIONS_INTERDITES = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'phtml', 'phps', 'pht',
        'pl', 'py', 'cgi', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'jar', 'htaccess', 'js', 'html', 'svg',
    ];
    private const TAILLE_MAXIMALE = 10 * 1024 * 1024; // 10 Mo : captures et reçus PDF

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Sous-dossier logique : justificatifs, attestations, travaux... */
    #[ORM\Column(length: 100)]
    #[Groups(['fichier:read'])]
    private string $path = 'documents';

    /** Nom stocké sur disque (unique). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['fichier:read'])]
    private ?string $originalName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['fichier:read'])]
    private ?string $mimeType = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['fichier:read'])]
    private ?string $url = null; // extension

    #[ORM\Column(nullable: true)]
    #[Groups(['fichier:read'])]
    private ?int $size = null;

    #[ORM\Column(length: 10)]
    private string $visibilite = self::PRIVE;

    private ?UploadedFile $file = null;
    private ?string $rawContent = null;
    private ?string $tempFilename = null;
    private string $projectDir = '';
    private string $privateDir = '';

    public function getId(): ?int { return $this->id; }
    public function getPath(): string { return $this->path; }
    public function setPath(string $path): self { $this->path = trim(preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $path), '/') ?: 'documents'; return $this; }
    public function getAlt(): ?string { return $this->alt; }
    public function getOriginalName(): ?string { return $this->originalName; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function getUrl(): ?string { return $this->url; }
    public function getSize(): ?int { return $this->size; }
    public function getVisibilite(): string { return $this->visibilite; }
    public function setVisibilite(string $v): self { $this->visibilite = $v === self::PUBLIC ? self::PUBLIC : self::PRIVE; return $this; }
    public function isPrive(): bool { return $this->visibilite === self::PRIVE; }

    public function setDirs(string $projectDir, string $privateDir): self
    {
        $this->projectDir = rtrim($projectDir, '/');
        $this->privateDir = rtrim($privateDir, '/');
        return $this;
    }

    public function setFile(?UploadedFile $file): void
    {
        if ($file !== null) {
            $extension = strtolower($file->getClientOriginalExtension() ?: '');
            if ($extension === '') {
                throw new \InvalidArgumentException('Le fichier doit porter une extension.');
            }
            if (in_array($extension, self::EXTENSIONS_INTERDITES, true)) {
                throw new \InvalidArgumentException(sprintf('Les fichiers « %s » ne sont pas acceptés.', $extension));
            }
            if (($taille = $file->getSize()) !== null && $taille > self::TAILLE_MAXIMALE) {
                throw new \InvalidArgumentException(sprintf('Fichier trop volumineux : %.1f Mo pour un maximum de %d Mo.', $taille / 1048576, self::TAILLE_MAXIMALE / 1048576));
            }
            $this->mimeType = $file->getMimeType() ?: $file->getClientMimeType();
            $this->originalName = $file->getClientOriginalName();
            $this->size = $file->getSize();
        }
        $this->file = $file;
    }

    /** Pour les fichiers générés côté serveur (attestations PDF) : pas d'UploadedFile. */
    public function setRawContent(string $bytes, string $originalName, string $mimeType): self
    {
        $this->rawContent = $bytes;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = strlen($bytes);
        return $this;
    }

    #[ORM\PrePersist]
    public function preUpload(): void
    {
        if ($this->file === null && $this->rawContent === null) {
            return;
        }
        $extension = strtolower(pathinfo($this->originalName ?? '', PATHINFO_EXTENSION));
        $base = substr(preg_replace('/[^a-zA-Z0-9-_]/', '_', pathinfo($this->originalName ?? 'fichier', PATHINFO_FILENAME)), 0, 80);
        $this->url = substr($extension, 0, 10);
        $this->alt = $base.'_'.bin2hex(random_bytes(8)).($extension ? '.'.$extension : '');
    }

    #[ORM\PostPersist]
    public function upload(): void
    {
        if ($this->file === null && $this->rawContent === null) {
            return;
        }
        $dir = $this->getUploadRootDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Impossible de créer le répertoire "%s".', $dir));
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException(sprintf('Impossible d\'écrire dans le répertoire "%s".', $dir));
        }
        if ($this->file !== null) {
            $this->file->move($dir, $this->alt);
            $this->file = null;
        } else {
            file_put_contents($dir.'/'.$this->alt, $this->rawContent);
            $this->rawContent = null;
        }
    }

    #[ORM\PreRemove]
    public function preRemoveUpload(): void
    {
        $this->tempFilename = $this->getAbsolutePath();
    }

    #[ORM\PostRemove]
    public function removeUpload(): void
    {
        if ($this->tempFilename !== null && is_file($this->tempFilename)) {
            unlink($this->tempFilename);
        }
    }

    public function getAbsolutePath(): string
    {
        return $this->getUploadRootDir().'/'.$this->alt;
    }

    /** URL web : uniquement pour les fichiers publics. Les privés passent par l'API. */
    #[Groups(['fichier:read'])]
    public function getWebPath(): ?string
    {
        return $this->isPrive() ? null : '/uploads/'.$this->path.'/'.$this->alt;
    }

    private function getUploadRootDir(): string
    {
        $base = $this->isPrive() ? $this->privateDir : $this->projectDir.'/public/uploads';
        if ($base === '') {
            throw new \LogicException('Les répertoires de stockage ne sont pas initialisés (FichierProjectDirListener).');
        }
        return $base.'/'.$this->path;
    }
}
