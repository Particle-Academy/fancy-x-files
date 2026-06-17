<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Contracts;

use ParticleAcademy\XFiles\Registry;

/**
 * A single "well-known" file served at a fixed path.
 *
 * Implementations are framework-agnostic value builders: they own their path,
 * their content type, and how to render their body. The {@see Registry}
 * collects them and any host (Laravel, plain PHP, a CLI) serves them.
 */
interface WellKnownFile
{
    /**
     * The request path this file is served at, with a leading slash.
     * Examples: "/robots.txt", "/.well-known/security.txt", "/sitemap.xml".
     */
    public function path(): string;

    /**
     * The MIME content type for the rendered body, e.g. "text/plain".
     */
    public function contentType(): string;

    /**
     * Render the file body as a string.
     */
    public function render(): string;

    /**
     * Validate the file; return a list of human-readable issue strings.
     * An empty array means the file is valid.
     *
     * @return list<string>
     */
    public function validate(): array;
}
