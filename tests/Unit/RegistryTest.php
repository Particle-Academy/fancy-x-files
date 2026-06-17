<?php

declare(strict_types=1);

use ParticleAcademy\XFiles\Files\HumansTxt;
use ParticleAcademy\XFiles\Files\RobotsTxt;
use ParticleAcademy\XFiles\Files\Sitemap;
use ParticleAcademy\XFiles\Laravel\HonorsRobots;
use ParticleAcademy\XFiles\Registry;

it('adds, gets, renders, and lists paths', function (): void {
    $registry = new Registry;
    $registry
        ->add(RobotsTxt::make()->userAgent('*')->disallow('/x'))
        ->add(HumansTxt::make()->section('TEAM', [['label' => 'Dev', 'value' => 'Ada']]));

    expect($registry->paths())->toContain('/robots.txt')->toContain('/humans.txt')
        ->and($registry->get('/robots.txt'))->toBeInstanceOf(RobotsTxt::class)
        // normalizes a leading slash
        ->and($registry->get('robots.txt'))->toBeInstanceOf(RobotsTxt::class)
        ->and($registry->has('/humans.txt'))->toBeTrue()
        ->and($registry->get('/missing.txt'))->toBeNull()
        ->and($registry->render('/robots.txt'))->toContain('Disallow: /x')
        ->and($registry->render('/missing.txt'))->toBeNull()
        ->and($registry->all())->toHaveCount(2);
});

it('validates the whole registry and reports per-file issues', function (): void {
    $registry = new Registry;
    $registry->add(Sitemap::make()); // empty => invalid

    $issues = $registry->validate();

    expect($issues)->toHaveKey('/sitemap.xml')
        ->and($issues['/sitemap.xml'])->toContain('sitemap.xml has no URLs');
});

it('reports an empty registry', function (): void {
    expect((new Registry)->validate())->toHaveKey('');
});

// --- HonorsRobots shared guard ------------------------------------------------

it('exposes one honest robots guard for scrapers', function (): void {
    $robots = "User-agent: *\nDisallow: /private\n";

    expect(HonorsRobots::allows($robots, '/public'))->toBeTrue()
        ->and(HonorsRobots::allows($robots, '/private'))->toBeFalse();

    $guard = HonorsRobots::forRobotsTxt($robots);
    expect($guard->mayFetch('/public'))->toBeTrue()
        ->and($guard->mayFetch('/private'))->toBeFalse();
});
