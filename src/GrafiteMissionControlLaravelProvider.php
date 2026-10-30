<?php

namespace Grafite\MissionControlLaravel;

use Exception;
use MatthiasMullie\Minify\JS;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Console\Scheduling\Schedule;
use Grafite\MissionControlLaravel\Commands\Code;
use Grafite\MissionControlLaravel\Commands\Stats;
use Grafite\MissionControlLaravel\Commands\Notify;
use Grafite\MissionControlLaravel\Commands\Report;
use Grafite\MissionControlLaravel\Commands\SSHLogger;
use Grafite\MissionControlLaravel\Commands\VirusScan;
use Grafite\MissionControlLaravel\Commands\QueueStats;
use Grafite\MissionControlLaravel\Commands\Dependencies;
use Grafite\MissionControlLaravel\Middleware\Firewall;

class GrafiteMissionControlLaravelProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mission-control.php', 'mission-control');

        $this->commands([
            Code::class,
            Stats::class,
            Notify::class,
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

        if ($alias = config('mission-control.firewall.alias', 'firewall')) {
            $this->app['router']->aliasMiddleware($alias, Firewall::class);
        }

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
    const nav = navigator;
    const brands = nav.userAgentData && nav.userAgentData.brands ? nav.userAgentData.brands : [];

    // Hard automation markers (Selenium, Puppeteer, Playwright,
    // PhantomJS, Nightmare, chromedriver) mean this is not a
    // human visit, so we skip logging the traffic entirely.
    const automation = nav.webdriver === true
        || ['_phantom', 'callPhantom', '__nightmare', 'domAutomation', 'domAutomationController'].some((key) => key in window)
        || Object.keys(document).concat(Object.keys(window)).some((key) => key.includes('cdc_') || /^__(webdriver|selenium|driver)/.test(key))
        || brands.some((brand) => /HeadlessChrome/i.test(brand.brand));

    if (automation) {
        return;
    }

    // Weak signals are sent along so Mission Control can score the visit.
    const signals = [];

    if (window.outerWidth === 0 || window.outerHeight === 0) {
        signals.push('no_outer_dimensions');
    }

    if (!nav.languages || nav.languages.length === 0) {
        signals.push('no_languages');
    }

    try {
        const gl = document.createElement('canvas').getContext('webgl');
        const info = gl && gl.getExtension('WEBGL_debug_renderer_info');

        if (info && /SwiftShader|llvmpipe|Software/i.test(gl.getParameter(info.UNMASKED_RENDERER_WEBGL))) {
            signals.push('software_renderer');
        }

        const lose = gl && gl.getExtension('WEBGL_lose_context');
        lose && lose.loseContext();
    } catch (e) {}

    if (window.Notification && nav.permissions) {
        nav.permissions.query({ name: 'notifications' }).then((status) => {
            if (Notification.permission === 'denied' && status.state === 'prompt') {
                signals.push('permission_mismatch');
            }
        }).catch(() => {});
    }

    // Only trusted input events count as interaction, scripted
    // dispatchEvent calls are never trusted. Scroll is excluded
    // since programmatic scrolling still produces trusted events.
    const interactionEvents = ['pointermove', 'pointerdown', 'wheel', 'keydown', 'touchstart'];
    let visibleAt = null;
    let sent = false;

    const onInteraction = (event) => {
        if (event.isTrusted) {
            send(true);
        }
    };

    // Sent once: on the first interaction, or when the page is hidden.
    // dwell_ms is the visible time before sending.
    const send = (interacted) => {
        if (sent || visibleAt === null) {
            return;
        }

        sent = true;
        interactionEvents.forEach((name) => window.removeEventListener(name, onInteraction, true));

        // Server response (TTFB minus DNS/TCP/redirects) and DOMContentLoaded
        // relative to responseStart, both in milliseconds.
        const timing = performance.getEntriesByType('navigation')[0];
        const ms = (value) => timing && value > 0 ? Math.round(value) : null;

        fetch("${url}/api/webhook/${uuid}/traffic?key=${key}", {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json; charset=UTF-8' },
            body: JSON.stringify({
                "referrer": document.referrer,
                "pathname": window.location.pathname,
                "hash": window.location.hash,
                "search": window.location.search,
                "interacted": interacted,
                "dwell_ms": Math.round(performance.now() - visibleAt),
                "response_ms": ms(timing && timing.responseStart - timing.requestStart),
                "load_ms": ms(timing && timing.domContentLoadedEventEnd - timing.responseStart),
                "signals": signals,
            }),
        }).catch(() => {});
    };

    // Prerendered and background tabs are not visits until they are seen.
    const start = () => {
        if (visibleAt !== null || document.visibilityState !== 'visible' || document.prerendering) {
            return;
        }

        visibleAt = performance.now();
        interactionEvents.forEach((name) => window.addEventListener(name, onInteraction, { capture: true, passive: true }));
    };

    document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' ? send(false) : start());
    document.addEventListener('prerenderingchange', start);
    window.addEventListener('pagehide', () => send(false));
    start();
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
             * Schedule the Mission Control commands.
             */
            if (config('mission-control.schedule.enabled', false)) {
                $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                    $this->schedule($schedule);
                });
            }

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
             * Log the emails sent by the application.
             */
            if (config('mission-control.log_emails', false)) {
                app('events')->listen(MessageSent::class, function (MessageSent $message) {
                    try {
                        app(Email::class)->record($message);
                    } catch (Exception $exception) {
                        return;
                    }
                });
            }

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

    /**
     * Schedule the Mission Control commands.
     */
    protected function schedule(Schedule $schedule)
    {
        // These commands need an action class in the app.
        $requires = [
            'stats' => 'App\\Actions\\AppStats',
            'dependencies' => 'App\\Actions\\AppDependencies',
        ];

        $output = config('mission-control.schedule.output');

        foreach (config('mission-control.schedule.commands', []) as $name => $cron) {
            if (! $cron || (isset($requires[$name]) && ! class_exists($requires[$name]))) {
                continue;
            }

            $event = $schedule->command("mission-control:{$name}")
                ->cron($cron)
                ->runInBackground()
                ->withoutOverlapping();

            if ($output) {
                $event->appendOutputTo($output);
            }
        }
    }
}
