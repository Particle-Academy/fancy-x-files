<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Files;

use ParticleAcademy\XFiles\Contracts\WellKnownFile;

/**
 * Builder for /llms.txt (the llms.txt markdown convention):
 * an H1 title, an optional summary blockquote, free-form details, then
 * sections of curated links.
 */
final class LlmsTxt implements WellKnownFile
{
    private string $title;

    private ?string $summary = null;

    private ?string $details = null;

    /** @var list<array{name: string, links: list<array{title: string, url: string, notes?: string}>}> */
    private array $sections = [];

    public function __construct(string $title = '')
    {
        $this->title = $title;
    }

    public static function make(string $title = ''): self
    {
        return new self($title);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $llms = new self((string) ($config['title'] ?? ''));
        if (isset($config['summary'])) {
            $llms->summary((string) $config['summary']);
        }
        if (isset($config['details'])) {
            $llms->details((string) $config['details']);
        }

        /** @var list<array<string, mixed>> $sections */
        $sections = $config['sections'] ?? [];
        foreach ($sections as $section) {
            /** @var list<array{title?: string, url?: string, notes?: string}> $links */
            $links = $section['links'] ?? [];
            $llms->section((string) ($section['name'] ?? ''), array_map(
                fn (array $l): array => array_filter([
                    'title' => (string) ($l['title'] ?? ''),
                    'url' => (string) ($l['url'] ?? ''),
                    'notes' => isset($l['notes']) ? (string) $l['notes'] : null,
                ], fn ($v): bool => $v !== null),
                $links,
            ));
        }

        return $llms;
    }

    public function path(): string
    {
        return '/llms.txt';
    }

    public function contentType(): string
    {
        return 'text/markdown';
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function summary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function details(string $details): self
    {
        $this->details = $details;

        return $this;
    }

    /**
     * @param  list<array{title: string, url: string, notes?: string}>  $links
     */
    public function section(string $name, array $links): self
    {
        $this->sections[] = ['name' => $name, 'links' => $links];

        return $this;
    }

    public function render(): string
    {
        $out = [];
        $out[] = '# '.$this->title;

        if ($this->summary !== null && $this->summary !== '') {
            $out[] = '';
            $out[] = '> '.$this->summary;
        }

        if ($this->details !== null && $this->details !== '') {
            $out[] = '';
            $out[] = $this->details;
        }

        foreach ($this->sections as $section) {
            $out[] = '';
            $out[] = '## '.$section['name'];
            $out[] = '';
            foreach ($section['links'] as $link) {
                $line = sprintf('- [%s](%s)', $link['title'], $link['url']);
                if (isset($link['notes']) && $link['notes'] !== '') {
                    $line .= ': '.$link['notes'];
                }
                $out[] = $line;
            }
        }

        return implode("\n", $out)."\n";
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $issues = [];

        if (trim($this->title) === '') {
            $issues[] = 'llms.txt requires a title (H1)';
        }

        foreach ($this->sections as $section) {
            foreach ($section['links'] as $link) {
                if (($link['url'] ?? '') === '') {
                    $issues[] = sprintf('llms.txt link "%s" in section "%s" has no url', $link['title'] ?? '', $section['name']);
                }
            }
        }

        return $issues;
    }
}
