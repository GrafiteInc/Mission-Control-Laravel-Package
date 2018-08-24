<?php

use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected $app;

    protected function getEnvironmentSetUp($app)
    {
        $app->make('Illuminate\Contracts\Http\Kernel');
    }

    protected function getPackageProviders($app)
    {
        return [
            \Grafite\MissionControlLaravel\GrafiteMissionControlLaravelProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [];
    }

    public function setUp()
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->withoutEvents();

        Config::set('mission-control.api_token', 'testing');
        Config::set('mission-control.webhook', 'testing');
        Config::set('mission-control.access_log_file_path', __DIR__.'/fixtures/access.log_example');
        Config::set('mission-control.format', '%a %l %u %t "%m %U %H" %>s %O "%{Referer}i" \"%{User-Agent}i"');
    }
}
