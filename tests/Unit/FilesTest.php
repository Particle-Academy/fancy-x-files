<?php

declare(strict_types=1);

use ParticleAcademy\XFiles\Files\AgentsTxt;
use ParticleAcademy\XFiles\Files\HumansTxt;
use ParticleAcademy\XFiles\Files\LlmsTxt;
use ParticleAcademy\XFiles\Files\SecurityTxt;
use ParticleAcademy\XFiles\Files\Sitemap;

// --- SecurityTxt --------------------------------------------------------------

it('renders security.txt fields one per line', function (): void {
    $sec = SecurityTxt::make()
        ->contact('mailto:sec@example.test')
        ->contact('https://example.test/report')
        ->expires(new DateTimeImmutable('+1 year'))
        ->encryption('https://example.test/pgp.txt')
        ->preferredLanguage('en', 'fr')
        ->canonical('https://example.test/.well-known/security.txt')
        ->policy('https://example.test/policy');

    $out = $sec->render();

    expect($sec->path())->toBe('/.well-known/security.txt')
        ->and($out)->toContain('Contact: mailto:sec@example.test')
        ->and($out)->toContain('Contact: https://example.test/report')
        ->and($out)->toContain('Expires: ')
        ->and($out)->toContain('Encryption: https://example.test/pgp.txt')
        ->and($out)->toContain('Preferred-Languages: en, fr')
        ->and($out)->toContain('Canonical: ')
        ->and($out)->toContain('Policy: https://example.test/policy');

    expect($sec->validate())->toBe([]);
});

it('flags missing contact and past expiry in security.txt', function (): void {
    $missingContact = SecurityTxt::make()->expires(new DateTimeImmutable('+1 day'));
    expect($missingContact->validate())
        ->toContain('security.txt requires at least one Contact field');

    $pastExpiry = SecurityTxt::make()
        ->contact('mailto:sec@example.test')
        ->expires(new DateTimeImmutable('-1 day'));
    expect($pastExpiry->validate())
        ->toContain('security.txt Expires is in the past — update it');
});

it('accepts an expires string', function (): void {
    $sec = SecurityTxt::make()->contact('mailto:x@y.z')->expires('2099-01-01T00:00:00+00:00');
    expect($sec->render())->toContain('Expires: 2099-01-01T00:00:00+00:00')
        ->and($sec->validate())->toBe([]);
});

// --- LlmsTxt ------------------------------------------------------------------

it('renders the llms.txt markdown format', function (): void {
    $llms = LlmsTxt::make('Acme Docs')
        ->summary('Everything an LLM needs about Acme.')
        ->details('Acme builds widgets.')
        ->section('Docs', [
            ['title' => 'Guide', 'url' => 'https://example.test/guide', 'notes' => 'start here'],
            ['title' => 'API', 'url' => 'https://example.test/api'],
        ]);

    $out = $llms->render();

    expect($llms->path())->toBe('/llms.txt')
        ->and($out)->toStartWith('# Acme Docs')
        ->and($out)->toContain('> Everything an LLM needs about Acme.')
        ->and($out)->toContain('Acme builds widgets.')
        ->and($out)->toContain('## Docs')
        ->and($out)->toContain('- [Guide](https://example.test/guide): start here')
        ->and($out)->toContain('- [API](https://example.test/api)');

    expect($llms->validate())->toBe([]);
});

it('flags a missing llms.txt title', function (): void {
    expect(LlmsTxt::make('')->validate())->toContain('llms.txt requires a title (H1)');
});

// --- HumansTxt ----------------------------------------------------------------

it('renders humans.txt sections of label: value', function (): void {
    $humans = HumansTxt::make()
        ->section('TEAM', [
            ['label' => 'Developer', 'value' => 'Ada'],
        ])
        ->line('THANKS', 'Coffee', 'always');

    $out = $humans->render();

    expect($humans->path())->toBe('/humans.txt')
        ->and($out)->toContain('/* TEAM */')
        ->and($out)->toContain('Developer: Ada')
        ->and($out)->toContain('/* THANKS */')
        ->and($out)->toContain('Coffee: always');
});

// --- Sitemap ------------------------------------------------------------------

it('renders a well-formed sitemap urlset', function (): void {
    $sitemap = Sitemap::make()
        ->url('https://example.test/', '2026-01-01', 'daily', '1.0')
        ->url('https://example.test/about');

    $out = $sitemap->render();

    expect($sitemap->path())->toBe('/sitemap.xml')
        ->and($sitemap->contentType())->toBe('application/xml')
        ->and($out)->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
        ->and($out)->toContain('<loc>https://example.test/</loc>')
        ->and($out)->toContain('<lastmod>2026-01-01</lastmod>')
        ->and($out)->toContain('<changefreq>daily</changefreq>')
        ->and($out)->toContain('<priority>1.0</priority>');

    // Parses as valid XML.
    $xml = simplexml_load_string($out);
    expect($xml)->not->toBeFalse()
        ->and(count($xml->url))->toBe(2);

    expect($sitemap->validate())->toBe([]);
});

it('escapes special characters in sitemap loc', function (): void {
    $out = Sitemap::make()->url('https://example.test/?a=1&b=2')->render();
    expect($out)->toContain('https://example.test/?a=1&amp;b=2');
    expect(simplexml_load_string($out))->not->toBeFalse();
});

it('flags an empty sitemap', function (): void {
    expect(Sitemap::make()->validate())->toContain('sitemap.xml has no URLs');
});

// --- AgentsTxt ----------------------------------------------------------------

it('renders an agents manifest as markdown', function (): void {
    $agents = AgentsTxt::make('Acme Agents')
        ->intro('What agents may do here.')
        ->capability('read public docs')
        ->contact('mailto:agents@example.test')
        ->policy('https://example.test/agent-policy');

    $out = $agents->render();

    expect($agents->path())->toBe('/ai.txt')
        ->and($out)->toStartWith('# Acme Agents')
        ->and($out)->toContain('What agents may do here.')
        ->and($out)->toContain('- Capability: read public docs')
        ->and($out)->toContain('- Contact: mailto:agents@example.test')
        ->and($out)->toContain('- Policy: https://example.test/agent-policy');
});

it('can serve the agents manifest at .well-known/agents.md', function (): void {
    expect(AgentsTxt::make('X')->at('/.well-known/agents.md')->path())
        ->toBe('/.well-known/agents.md');
});
