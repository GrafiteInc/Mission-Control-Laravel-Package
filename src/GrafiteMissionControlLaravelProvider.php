<?php

namespace Grafite\MissionControlLaravel;

use Exception;
use MatthiasMullie\Minify\JS;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Grafite\MissionControlLaravel\Commands\Code;
use Grafite\MissionControlLaravel\Commands\Stats;
use Grafite\MissionControlLaravel\Commands\Report;
use Grafite\MissionControlLaravel\Commands\SSHLogger;
use Grafite\MissionControlLaravel\Commands\VirusScan;
use Grafite\MissionControlLaravel\Commands\QueueStats;
use Grafite\MissionControlLaravel\Commands\Dependencies;

class GrafiteMissionControlLaravelProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->commands([
            Code::class,
            Stats::class,
            QueueStats::class,
            Dependencies::class,
            Report::class,
            SSHLogger::class,
            VirusScan::class,
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

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../views', 'support');

        $enabledEnvironments = config('mission-control.environments', ['production']);
        $missionControlEnabled = app()->environment($enabledEnvironments)
            && ! is_null(config('mission-control.api_token'))
            && ! is_null(config('mission-control.api_key'));

        app('blade.compiler')->directive('missionControl', function ($nonce) use ($missionControlEnabled) {
            $nonce = $nonce ? ' nonce="' . $nonce . '"' : '';

            if (! $missionControlEnabled) {
                return '';
            }

            $url = config('mission-control.url');
            $uuid = config('mission-control.api_uuid');
            $key = config('mission-control.api_key');
            $standardPageLoadTime = config('mission-control.page_load_threshold', 2.5);
            $logJSErrors = config('mission-control.log_javascript_errors', false);
            $logTraffic = config('mission-control.log_traffic', true);

            $logJSErrorsScript = '';
            $logTrafficScript = '';

            if ($logTraffic) {
                $logTrafficScript = <<<JS
window.addEventListener('load', () => {
    const xhttp = new XMLHttpRequest();
    xhttp.open("POST", "${url}/api/webhook/${uuid}/traffic?key=${key}", true);
    xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
    xhttp.send(JSON.stringify({
        "referrer": document.referrer,
        "pathname": window.location.pathname,
        "hash": window.location.hash,
        "search": window.location.search,
    }));

    return false;
});
JS;
            }

            if ($logJSErrors) {
                $logJSErrorsScript = <<<JS
window.addEventListener('error', function (event) {
    const mainHTMLsize = (new Blob([new XMLSerializer().serializeToString(document)], {type: 'text/html'})).size;
    const xhttp = new XMLHttpRequest();
    xhttp.open("POST", "${url}/api/webhook/${uuid}/issue?key=${key}", true);
    xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
    xhttp.send(JSON.stringify({
        source: 'JavaScript',
        message: `\${event.message}: on line \${event.lineno} at column \${event.colno} within \${event.filename}`,
        stack: event.error.stack,
        user_agent: navigator.userAgent,
        connection: navigator.connection ? navigator.connection.effectiveType : 'unknown',
        page_size: mainHTMLsize,
        tag: "error"
    }));

    return false;
});
JS;
            }

            $script = <<<JS
{$logTrafficScript}
{$logJSErrorsScript}

window.addEventListener('load', () => {
	const observer = new PerformanceObserver((list) => {
        list.getEntries().forEach((entry) => {
            // Time-to-interactive-ish: DOMContentLoaded relative to when the
            // server response started, excluding redirects, DNS, TCP/TLS and
            // TTFB so the value is comparable to Chrome DevTools.
            const loadTime = Number.parseFloat((entry.domContentLoadedEventEnd - entry.responseStart) / 1000).toFixed(2);
            const page = window.location.href;
            let standardPageLoadTime = {$standardPageLoadTime};
            const mainHTMLsize = (new Blob([new XMLSerializer().serializeToString(document)], {type: 'text/html'})).size;

            // If the browser supports touch points,
            // it is likely a mobile device and we
            // should allow for longer load times.
            if (navigator.maxTouchPoints > 1) {
                standardPageLoadTime = standardPageLoadTime * 2.15;
            }

            if (loadTime > standardPageLoadTime) {
                const xhttp = new XMLHttpRequest();
                xhttp.open("POST", "${url}/api/webhook/${uuid}/issue?key=${key}", true);
                xhttp.setRequestHeader("Content-Type", "application/json; charset=UTF-8");
                xhttp.send(JSON.stringify({
                    source: 'JavaScript',
                    message: `\${page} load time (\${loadTime} seconds) exceeded the standard page load time of (\${standardPageLoadTime} seconds)`,
                    stack: null,
                    user_agent: navigator.userAgent,
                    connection: navigator.connection ? navigator.connection.effectiveType : 'unknown',
                    page_size: mainHTMLsize,
                    tag: "info"
                }));
            }
        });
    });

    observer.observe({ type: "navigation", buffered: true });
});
JS;

            static $minifiedScriptCache = [];

            $cacheKey = md5(serialize([
                $url,
                $uuid,
                $key,
                $standardPageLoadTime,
                $logJSErrors,
                $logTraffic,
            ]));

            if (! isset($minifiedScriptCache[$cacheKey])) {
                $minifierJS = new JS();
                $minifierJS->add($script);
                $minifiedScriptCache[$cacheKey] = $minifierJS->minify();
            }

            return "<!-- MISSION CONTROL -->\n<script type=\"module\" {$nonce}>" . $minifiedScriptCache[$cacheKey] . '</script>';
        });

        if ($missionControlEnabled) {
            /**
             * General app error logging
             */
            $levels = array_flip(config('mission-control.levels', [
                'emergency',
                'alert',
                'critical',
                'error',
            ]));

            app('log')->listen(function (MessageLogged $message) use ($levels) {
                if (isset($levels[$message->level])) {
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
            $standardQueryTimeMs = $standardQueryTime * 1000;

            DB::listen(function ($sql) use ($standardQueryTime, $standardQueryTimeMs) {
                if ($sql->time >= $standardQueryTimeMs) {
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

            $queueConnections = config('queue.connections', []);
            $connectionPrefixes = [];
            $supportsAtomicIncrement = method_exists(cache()->getStore(), 'increment');

            foreach ($queueConnections as $name => $connection) {
                $connectionPrefixes[$name] = $connection['prefix'] ?? null;
            }

            $monitoredQueues = config('mission-control.queues', []);

            Queue::before(function (JobProcessing $event) use ($connectionPrefixes, $monitoredQueues, $supportsAtomicIncrement) {
                $queue = $event->job->getQueue();
                $connection = $event->connectionName;

                // Queue names can include a configured prefix.
                if (! is_null($connectionPrefixes[$connection] ?? null)) {
                    $queue = str_replace('/', '', str_replace($connectionPrefixes[$connection], '', $queue));
                }

                if (($monitoredQueues[$queue] ?? null) !== $connection) {
                    return;
                }

                $cacheName = 'mission-control-initiated-'.$connection.'-'.$queue.'-jobs';
                $ttl = now()->addDays(2)->endOfDay();

                if ($supportsAtomicIncrement) {
                    cache()->add($cacheName, 0, $ttl);
                    cache()->increment($cacheName);

                    return;
                }

                cache()->put($cacheName, cache($cacheName, 0) + 1, $ttl);
            });

            Queue::after(function (JobProcessed $event) use ($connectionPrefixes, $monitoredQueues, $supportsAtomicIncrement) {
                $queue = $event->job->getQueue();
                $connection = $event->connectionName;

                // Queue names can include a configured prefix.
                if (! is_null($connectionPrefixes[$connection] ?? null)) {
                    $queue = str_replace('/', '', str_replace($connectionPrefixes[$connection], '', $queue));
                }

                if (($monitoredQueues[$queue] ?? null) !== $connection) {
                    return;
                }

                $cacheName = 'mission-control-processed-'.$connection.'-'.$queue.'-jobs';
                $ttl = now()->addDays(2)->endOfDay();

                if ($supportsAtomicIncrement) {
                    cache()->add($cacheName, 0, $ttl);
                    cache()->increment($cacheName);

                    return;
                }

                cache()->put($cacheName, cache($cacheName, 0) + 1, $ttl);
            });
        }

        return $this;
    }
}
