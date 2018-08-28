<?php

namespace Grafite\MissionControlLaravel;

use Grafite\MissionControlLaravel\Commands\Report;
use Illuminate\Support\ServiceProvider;

class GrafiteMissionControlLaravelProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->commands([
            Report::class,
        ]);
    }

    /**
     * Boot method.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config' => base_path('config'),
        ]);
    }
}
