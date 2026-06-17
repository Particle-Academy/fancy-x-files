<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Files;

use ParticleAcademy\XFiles\Contracts\WellKnownFile;

/**
 * Builder for /humans.txt — simple sections (TEAM, THANKS, SITE) of
 * `label: value` lines.
 */
final class HumansTxt implements WellKnownFile
{
    /** @var list<array{name: string, entries: list<array{label: string, value: string}>}> */
    private array $sections = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $humans = new self;

        /** @var list<array<string, mixed>> $sections */
        $sections = $config['sections'] ?? [];
        foreach ($sections as $section) {
            $entries = [];
            /** @var array<string, mixed> $rawEntries */
            $rawEntries = $section['entries'] ?? [];
            foreach ($rawEntries as $label => $value) {
                // Support both ['label' => 'value'] maps and [['label','value'], ...] lists.
                if (is_array($value) && isset($value['label'])) {
                    $entries[] = ['label' => (string) $value['label'], 'value' => (string) ($value['value'] ?? '')];
                } else {
                    $entries[] = ['label' => (string) $label, 'value' => (string) $value];
                }
            }
            $humans->section((string) ($section['name'] ?? ''), $entries);
        }

        return $humans;
    }

    public function path(): string
    {
        return '/humans.txt';
    }

    public function contentType(): string
    {
        return 'text/plain';
    }

    /**
     * @param  list<array{label: string, value: string}>  $entries
     */
    public function section(string $name, array $entries): self
    {
        $this->sections[] = ['name' => $name, 'entries' => $entries];

        return $this;
    }

    public function line(string $sectionName, string $label, string $value): self
    {
        foreach ($this->sections as $i => $section) {
            if ($section['name'] === $sectionName) {
                $this->sections[$i]['entries'][] = ['label' => $label, 'value' => $value];

                return $this;
            }
        }

        return $this->section($sectionName, [['label' => $label, 'value' => $value]]);
    }

    public function render(): string
    {
        $out = [];

        foreach ($this->sections as $index => $section) {
            if ($index > 0) {
                $out[] = '';
            }
            $out[] = '/* '.$section['name'].' */';
            foreach ($section['entries'] as $entry) {
                $out[] = $entry['label'].': '.$entry['value'];
            }
        }

        return implode("\n", $out)."\n";
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        if ($this->sections === []) {
            return ['humans.txt has no sections'];
        }

        return [];
    }
}
