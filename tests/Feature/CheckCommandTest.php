<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use ParticleAcademy\XFiles\Files\RobotsTxt;
use ParticleAcademy\XFiles\Files\SecurityTxt;
use ParticleAcademy\XFiles\Registry;

it('passes x-files:check on a valid set', function (): void {
    config()->set('x-files.files', function (Registry $registry): void {
        $registry
            ->add(RobotsTxt::make()->userAgent('*')->disallow('/admin'))
            ->add(SecurityTxt::make()->contact('mailto:sec@example.test')->expires(new DateTimeImmutable('+1 year')));
    });
    $this->app->forgetInstance(Registry::class);

    expect(Artisan::call('x-files:check'))->toBe(0);
});

it('fails x-files:check when a file is invalid', function (): void {
    config()->set('x-files.files', function (Registry $registry): void {
        // Past expiry + missing contact => invalid security.txt.
        $registry->add(SecurityTxt::make()->expires(new DateTimeImmutable('-1 day')));
    });
    $this->app->forgetInstance(Registry::class);

    expect(Artisan::call('x-files:check'))->toBe(1);
});
