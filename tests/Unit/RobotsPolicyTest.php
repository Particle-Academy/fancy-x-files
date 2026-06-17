<?php

declare(strict_types=1);

use ParticleAcademy\XFiles\Robots\RobotsPolicy;

it('is default-open when nothing matches', function (): void {
    $policy = RobotsPolicy::parse("User-agent: *\nDisallow: /private\n");

    expect($policy->allowed('/anything'))->toBeTrue()
        ->and($policy->allowed('/private'))->toBeFalse();
});

it('treats an empty robots.txt as fully open', function (): void {
    $policy = RobotsPolicy::parse('');

    expect($policy->allowed('/anything'))->toBeTrue();
});

it('treats an empty Disallow as allow-all', function (): void {
    $policy = RobotsPolicy::parse("User-agent: *\nDisallow:\n");

    expect($policy->allowed('/admin'))->toBeTrue();
});

it('lets the longest matching rule win', function (): void {
    $policy = RobotsPolicy::parse(
        "User-agent: *\n".
        "Disallow: /a\n".
        "Allow: /a/b\n"
    );

    // /a/b/c matches both; Allow /a/b is longer => allowed.
    expect($policy->allowed('/a/b/c'))->toBeTrue()
        // /a/x only matches Disallow /a => blocked.
        ->and($policy->allowed('/a/x'))->toBeFalse();
});

it('lets Allow beat Disallow at equal specificity (Google rule)', function (): void {
    $policy = RobotsPolicy::parse(
        "User-agent: *\n".
        "Disallow: /page\n".
        "Allow: /page\n"
    );

    expect($policy->allowed('/page'))->toBeTrue();
});

it('uses the UA-specific group over * when the UA matches', function (): void {
    $policy = RobotsPolicy::parse(
        "User-agent: *\n".
        "Disallow:\n".
        "\n".
        "User-agent: GPTBot\n".
        "Disallow: /\n"
    );

    expect($policy->allowed('/x', 'GPTBot'))->toBeFalse()
        ->and($policy->allowed('/x', 'Googlebot'))->toBeTrue()
        ->and($policy->allowed('/x'))->toBeTrue();
});

it('honors $ end-anchors and * wildcards', function (): void {
    $policy = RobotsPolicy::parse(
        "User-agent: *\n".
        "Disallow: /*.pdf$\n"
    );

    expect($policy->allowed('/files/report.pdf'))->toBeFalse()
        ->and($policy->allowed('/files/report.pdf?v=2'))->toBeTrue()
        ->and($policy->allowed('/files/report.txt'))->toBeTrue();
});
