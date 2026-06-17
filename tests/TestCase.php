<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ParticleAcademy\XFiles\Laravel\XFilesServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [XFilesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('app.name', 'Example');
    }
}
