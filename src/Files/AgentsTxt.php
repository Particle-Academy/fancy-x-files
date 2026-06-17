<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Files;

use ParticleAcademy\XFiles\Contracts\WellKnownFile;

/**
 * Builder for an agents / AI manifest — the llms.txt-adjacent file that tells
 * autonomous agents what they may do, how to identify themselves, and where the
 * policy lives. Served at /ai.txt by default (a sibling to /llms.txt); the path
 * is configurable so a host can also expose it at /.well-known/agents.md.
 *
 * Modeled as a thin markdown doc: a title, a free-form intro, and labeled lines
 * (Capability / Contact / Policy / …).
 */
final class AgentsTxt implements WellKnownFile
{
    private string $title;

    private ?string $intro = null;

    private string $path = '/ai.txt';

    /** @var list<array{label: string, value: string}> */
    private array $lines = [];

    public function __construct(string $title = 'Agent manifest')
    {
        $this->title = $title;
    }

    public static function make(string $title = 'Agent manifest'): self
    {
        return new self($title);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $agents = new self((string) ($config['title'] ?? 'Agent manifest'));
        if (isset($config['path'])) {
            $agents->at((string) $config['path']);
        }
        if (isset($config['intro'])) {
            $agents->intro((string) $config['intro']);
        }
        foreach ((array) ($config['capabilities'] ?? []) as $c) {
            $agents->capability((string) $c);
        }
        foreach ((array) ($config['contact'] ?? []) as $c) {
            $agents->contact((string) $c);
        }
        if (isset($config['policy'])) {
            $agents->policy((string) $config['policy']);
        }

        /** @var list<array{label?: string, value?: string}> $lines */
        $lines = $config['lines'] ?? [];
        foreach ($lines as $line) {
            $agents->line((string) ($line['label'] ?? ''), (string) ($line['value'] ?? ''));
        }

        return $agents;
    }

    /** Set the serving path (e.g. "/ai.txt" or "/.well-known/agents.md"). */
    public function at(string $path): self
    {
        $this->path = '/'.ltrim($path, '/');

        return $this;
    }

    public function path(): string
    {
        return $this->path;
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

    public function intro(string $intro): self
    {
        $this->intro = $intro;

        return $this;
    }

    public function line(string $label, string $value): self
    {
        $this->lines[] = ['label' => $label, 'value' => $value];

        return $this;
    }

    public function capability(string ...$values): self
    {
        foreach ($values as $v) {
            $this->line('Capability', $v);
        }

        return $this;
    }

    public function contact(string ...$values): self
    {
        foreach ($values as $v) {
            $this->line('Contact', $v);
        }

        return $this;
    }

    public function policy(string $url): self
    {
        return $this->line('Policy', $url);
    }

    public function render(): string
    {
        $out = [];
        $out[] = '# '.$this->title;

        if ($this->intro !== null && $this->intro !== '') {
            $out[] = '';
            $out[] = $this->intro;
        }

        if ($this->lines !== []) {
            $out[] = '';
            foreach ($this->lines as $line) {
                $out[] = '- '.$line['label'].': '.$line['value'];
            }
        }

        return implode("\n", $out)."\n";
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        if (trim($this->title) === '') {
            return ['agents manifest requires a title'];
        }

        return [];
    }
}
