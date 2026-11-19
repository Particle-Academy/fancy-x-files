<?php

declare(strict_types=1);

namespace ParticleAcademy\XFiles\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ParticleAcademy\XFiles\Laravel\XFilesServiceProvider;

/**
 * A hand-built Laravel kernel: laravel/framework only, no orchestra/testbench
 * (the same approach as change-council's and fancy-sign's harnesses). In-memory
 * SQLite, array drivers, blank provider keys: nothing leaves the process.
 */
class TestCase extends BaseTestCase
{
    /**
     * Artisan output is not mocked: that path needs Mockery, which arrived
     * only through testbench. Command tests assert Artisan::call()'s exit code.
     */
    public $mockConsoleOutput = false;

    public function createApplication(): Application
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fancy-x-files-tests';
        foreach (['app', 'config', 'bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs'] as $dir) {
            if (! is_dir($base.'/'.$dir)) {
                mkdir($base.'/'.$dir, 0777, true);
            }
        }
        // An empty .env: the framework reads one, and nothing in it may come from the machine.
        file_put_contents($base.'/.env', '');
        // Blade's component compiler asks the application for its namespace, read from composer.json.
        file_put_contents($base.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));

        $app = Application::configure(basePath: $base)
            ->withProviders([XFilesServiceProvider::class], withBootstrapProviders: false)
            ->withMiddleware(static function (Middleware $middleware): void {})
            ->withExceptions(static function (Exceptions $exceptions): void {})
            ->create();

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['env'] = 'testing';
            $this->defineEnvironment($app);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function defineEnvironment(Application $app): void
    {
        $config = $app['config'];

        $config->set('app.env', 'testing');
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('mail.default', 'array');
        $config->set('services.anthropic.key', '');
        $config->set('services.openai.key', '');

        $config->set('app.url', 'https://example.test');
        $config->set('app.name', 'Example');
    }
}
