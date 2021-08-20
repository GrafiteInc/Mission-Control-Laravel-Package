<?php

namespace Grafite\MissionControlLaravel;

use Exception;
use MatthiasMullie\Minify\JS;
use Illuminate\Support\Facades\DB;
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

        $this->app['blade.compiler']->directive('missionControl', function () {
            $url = config('mission-control.url');
            $uuid = config('mission-control.api_uuid');
            $key = config('mission-control.api_key');

            $script = <<<EOL
window.addEventListener('error', function (event) {
    const xhttp = new XMLHttpRequest();
    xhttp.open("POST", "${url}/api/webhook/${uuid}/issue?key=${key}", true);
    xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
    xhttp.send(JSON.stringify({
        source: 'JavaScript',
        message: `\${event.message}: on line \${event.lineno} at column \${event.colno} within \${event.filename}`,
        stack: event.error.stack,
        tag: "error"
    }));

    return false;
});
EOL;

            $minifierJS = new JS();

            if (app()->environment(config('mission-control.environments', ['production']))) {
                return '<script>' . $minifierJS->add($script)->minify() . '</script>';
            }

            return '';
        });

        if (
            app()->environment(config('mission-control.environments', ['production']))
            && ! is_null(config('mission-control.api_token'))
            && ! is_null(config('mission-control.api_key'))
        ) {
            /**
             * General app error logging
             */
            $this->app['log']->listen(function (MessageLogged $message) {
                if (in_array($message->level, config('mission-control.levels', [
                    'emergency',
                    'alert',
                    'critical',
                    'error',
                ]))) {
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
                }
            });

            /**
             * Handle the database query logging.
             */
            $standardQueryTime = config('mission-control.query_threshold', 5);

            DB::listen(function ($sql) use ($standardQueryTime) {
                if ($sql->time >= ($standardQueryTime * 1000)) {
                    $i = 0;

                    $statement = collect(explode(' ', $sql->sql))->map(function ($string) use ($sql, &$i) {
                        if ($string === '?') {
                            $string = $sql->bindings[$i];
                            $i++;
                        }

                        return $string;
                    })->implode(' ');

                    $message = "The following statement ({$sql->time} milliseconds) exceeded the standard query time (${standardQueryTime} seconds): <br><br><span class=\"text-info\">${statement}</span>";

                    app(Issue::class)->log($message, 'warning');
                }
            });
        }

        return $this;
    }
}
