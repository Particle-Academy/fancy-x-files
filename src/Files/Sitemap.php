<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Files;

use ParticleAcademy\XFiles\Contracts\WellKnownFile;

/**
 * Builder for /sitemap.xml — a sitemaps.org <urlset> of URL entries.
 */
final class Sitemap implements WellKnownFile
{
    /** @var list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}> */
    private array $urls = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $sitemap = new self;

        /** @var list<array<string, mixed>> $urls */
        $urls = $config['urls'] ?? [];
        foreach ($urls as $url) {
            $sitemap->url(
                (string) ($url['loc'] ?? ''),
                isset($url['lastmod']) ? (string) $url['lastmod'] : null,
                isset($url['changefreq']) ? (string) $url['changefreq'] : null,
                isset($url['priority']) ? (string) $url['priority'] : null,
            );
        }

        return $sitemap;
    }

    public function path(): string
    {
        return '/sitemap.xml';
    }

    public function contentType(): string
    {
        return 'application/xml';
    }

    public function url(string $loc, ?string $lastmod = null, ?string $changefreq = null, ?string $priority = null): self
    {
        $entry = ['loc' => $loc];
        if ($lastmod !== null) {
            $entry['lastmod'] = $lastmod;
        }
        if ($changefreq !== null) {
            $entry['changefreq'] = $changefreq;
        }
        if ($priority !== null) {
            $entry['priority'] = $priority;
        }
        $this->urls[] = $entry;

        return $this;
    }

    public function render(): string
    {
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($this->urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$this->escape($url['loc']).'</loc>';
            if (isset($url['lastmod'])) {
                $lines[] = '    <lastmod>'.$this->escape($url['lastmod']).'</lastmod>';
            }
            if (isset($url['changefreq'])) {
                $lines[] = '    <changefreq>'.$this->escape($url['changefreq']).'</changefreq>';
            }
            if (isset($url['priority'])) {
                $lines[] = '    <priority>'.$this->escape($url['priority']).'</priority>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $issues = [];

        if ($this->urls === []) {
            $issues[] = 'sitemap.xml has no URLs';
        }

        foreach ($this->urls as $i => $url) {
            if (($url['loc'] ?? '') === '') {
                $issues[] = sprintf('sitemap.xml url #%d is missing <loc>', $i + 1);
            }
        }

        return $issues;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
