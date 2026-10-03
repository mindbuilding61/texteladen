<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

/**
 * GoBD-oriented file storage: original invoice bytes are never modified.
 * Files are stored under %storage_dir%/<YYYY>/<MM>/<sha256>-<slug> and
 * referenced by storage key (relative path).
 */
class InvoiceStorage
{
    public function __construct(
        private readonly string $storageDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(string $content, string $originalFilename): string
    {
        $hash = hash('sha256', $content);
        $date = new \DateTimeImmutable();
        $relDir = sprintf('%s/%s', $date->format('Y'), $date->format('m'));
        $absDir = $this->storageDir.'/'.$relDir;
        $this->filesystem->mkdir($absDir, 0o755);

        $slug = $this->sanitize($originalFilename);
        $relPath = sprintf('%s/%s-%s', $relDir, substr($hash, 0, 16), $slug);
        $absPath = $this->storageDir.'/'.$relPath;

        if (!is_file($absPath)) {
            file_put_contents($absPath, $content, LOCK_EX);
            @chmod($absPath, 0o444);
        }

        return $relPath;
    }

    public function get(string $storageKey): string
    {
        $path = $this->absolutePath($storageKey);
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Stored file "%s" not found.', $storageKey));
        }
        return (string) file_get_contents($path);
    }

    public function absolutePath(string $storageKey): string
    {
        return $this->storageDir.'/'.ltrim($storageKey, '/');
    }

    public function exists(string $storageKey): bool
    {
        return is_file($this->absolutePath($storageKey));
    }

    private function sanitize(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'invoice';
        $name = trim($name, '._-');
        return $name !== '' ? substr($name, 0, 100) : 'invoice';
    }
}
