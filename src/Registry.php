<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles;

use ParticleAcademy\XFiles\Contracts\WellKnownFile;

/**
 * The single source of truth: a set of {@see WellKnownFile} instances keyed by path.
 *
 * Apps register their well-known files into one Registry and everything else —
 * routing, rendering, validation — reads from it, so robots.txt and friends
 * are defined once and served consistently.
 */
final class Registry
{
    /** @var array<string, WellKnownFile> */
    private array $files = [];

    public function add(WellKnownFile $file): self
    {
        $this->files[$file->path()] = $file;

        return $this;
    }

    public function get(string $path): ?WellKnownFile
    {
        return $this->files[$this->normalize($path)] ?? null;
    }

    public function has(string $path): bool
    {
        return isset($this->files[$this->normalize($path)]);
    }

    /**
     * @return list<WellKnownFile>
     */
    public function all(): array
    {
        return array_values($this->files);
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_keys($this->files);
    }

    public function render(string $path): ?string
    {
        return $this->get($path)?->render();
    }

    /**
     * Validate every registered file. Returns a map of path => list<issue>,
     * containing only the files that have issues. Empty array => all valid.
     *
     * @return array<string, list<string>>
     */
    public function validate(): array
    {
        $issues = [];

        if ($this->files === []) {
            return ['' => ['registry is empty: no well-known files registered']];
        }

        foreach ($this->files as $path => $file) {
            $fileIssues = $file->validate();
            if ($fileIssues !== []) {
                $issues[$path] = $fileIssues;
            }
        }

        return $issues;
    }

    /**
     * Normalize a path so "robots.txt" and "/robots.txt" resolve identically.
     */
    private function normalize(string $path): string
    {
        return '/'.ltrim($path, '/');
    }
}
