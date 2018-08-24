<?php

namespace Grafite\MissionControlLaravel;

use Illuminate\Support\ServiceProvider;

class GrafiteMissionControlLaravelProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        // Nothing here yet....
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
