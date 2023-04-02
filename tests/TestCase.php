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

    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Config::set('mission-control.api_token', 'testing');
        Config::set('mission-control.api_key', 'testing');
    }
}
