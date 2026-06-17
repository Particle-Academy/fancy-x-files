<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Files;

use DateTimeImmutable;
use DateTimeInterface;
use ParticleAcademy\XFiles\Contracts\WellKnownFile;

/**
 * Builder for /.well-known/security.txt (RFC 9116).
 *
 * Required: at least one Contact and an Expires in the future.
 */
final class SecurityTxt implements WellKnownFile
{
    /** @var list<string> */
    private array $contact = [];

    private ?DateTimeInterface $expires = null;

    /** @var list<string> */
    private array $encryption = [];

    /** @var list<string> */
    private array $acknowledgments = [];

    /** @var list<string> */
    private array $preferredLanguages = [];

    private ?string $canonical = null;

    private ?string $policy = null;

    private ?string $hiring = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $sec = new self;

        foreach ((array) ($config['contact'] ?? []) as $c) {
            $sec->contact((string) $c);
        }
        if (isset($config['expires'])) {
            $sec->expires($config['expires']);
        }
        foreach ((array) ($config['encryption'] ?? []) as $e) {
            $sec->encryption((string) $e);
        }
        foreach ((array) ($config['acknowledgments'] ?? []) as $a) {
            $sec->acknowledgments((string) $a);
        }
        foreach ((array) ($config['preferredLanguages'] ?? $config['preferred_languages'] ?? []) as $l) {
            $sec->preferredLanguage((string) $l);
        }
        if (isset($config['canonical'])) {
            $sec->canonical((string) $config['canonical']);
        }
        if (isset($config['policy'])) {
            $sec->policy((string) $config['policy']);
        }
        if (isset($config['hiring'])) {
            $sec->hiring((string) $config['hiring']);
        }

        return $sec;
    }

    public function path(): string
    {
        return '/.well-known/security.txt';
    }

    public function contentType(): string
    {
        return 'text/plain';
    }

    public function contact(string ...$contacts): self
    {
        foreach ($contacts as $c) {
            $this->contact[] = $c;
        }

        return $this;
    }

    /**
     * @param  DateTimeInterface|string  $when  A DateTimeInterface or any parseable date string.
     */
    public function expires(DateTimeInterface|string $when): self
    {
        $this->expires = $when instanceof DateTimeInterface ? $when : new DateTimeImmutable($when);

        return $this;
    }

    public function encryption(string ...$urls): self
    {
        foreach ($urls as $u) {
            $this->encryption[] = $u;
        }

        return $this;
    }

    public function acknowledgments(string ...$urls): self
    {
        foreach ($urls as $u) {
            $this->acknowledgments[] = $u;
        }

        return $this;
    }

    public function preferredLanguage(string ...$langs): self
    {
        foreach ($langs as $l) {
            $this->preferredLanguages[] = $l;
        }

        return $this;
    }

    public function canonical(string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function policy(string $url): self
    {
        $this->policy = $url;

        return $this;
    }

    public function hiring(string $url): self
    {
        $this->hiring = $url;

        return $this;
    }

    public function render(): string
    {
        $lines = [];

        foreach ($this->contact as $c) {
            $lines[] = 'Contact: '.$c;
        }
        if ($this->expires !== null) {
            $lines[] = 'Expires: '.$this->expires->format(DateTimeInterface::RFC3339);
        }
        foreach ($this->encryption as $e) {
            $lines[] = 'Encryption: '.$e;
        }
        foreach ($this->acknowledgments as $a) {
            $lines[] = 'Acknowledgments: '.$a;
        }
        if ($this->preferredLanguages !== []) {
            $lines[] = 'Preferred-Languages: '.implode(', ', $this->preferredLanguages);
        }
        if ($this->canonical !== null) {
            $lines[] = 'Canonical: '.$this->canonical;
        }
        if ($this->policy !== null) {
            $lines[] = 'Policy: '.$this->policy;
        }
        if ($this->hiring !== null) {
            $lines[] = 'Hiring: '.$this->hiring;
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $issues = [];

        if ($this->contact === []) {
            $issues[] = 'security.txt requires at least one Contact field';
        }

        if ($this->expires === null) {
            $issues[] = 'security.txt requires an Expires field';
        } elseif ($this->expires->getTimestamp() <= time()) {
            $issues[] = 'security.txt Expires is in the past — update it';
        }

        return $issues;
    }
}
