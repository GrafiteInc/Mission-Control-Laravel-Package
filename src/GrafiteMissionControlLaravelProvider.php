<?php

namespace Grafite\MissionControlLaravel;

use Exception;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use Grafite\MissionControlLaravel\Commands\Report;

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
            __DIR__ . '/../config/mission-control.php' => base_path('config/mission-control.php'),
        ]);

        if (app()->environment(config('mission-control.environments', ['production']))) {
            $this->app['log']->listen(function (MessageLogged $message) {
                try {
                    if (! empty($message->context['exception'])) {
                        app(Issue::class)->exception($message->context['exception']);
                    }

                    if (empty($message->context['exception'])) {
                        app(Issue::class)->log($message->message, $message->level);
                    }
                } catch (Exception $exception) {
                    return;
                }
            });
        }

        return $this;
    }
}
