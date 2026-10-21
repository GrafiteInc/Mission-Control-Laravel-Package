<?php

namespace Grafite\MissionControlLaravel\Middleware;

use Closure;
use Illuminate\Support\Str;
use Grafite\MissionControlLaravel\Security;
use Grafite\MissionControlLaravel\Events\AttackDetected;

class Firewall
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (app()->environment(config('mission-control.environments', ['production']))) {
            if ($this->shouldSkip($request)) {
                return $next($request);
            }

            $session = session();
            $ipAddress = $request->ip();
            $service = app(Security::class);
            $threat = null;

            // If they have already been validated as a good actor
            // but their IP has changed, we need to re-validate them.
            if ($session->get('mission-control.validated-actor')) {
                // If their IP has changed.
                if ($session->get('mission-control.ip') !== $ipAddress) {
                    $session->forget('mission-control');
                }

                // Is a malcious action
                if ($threat = $service->isMalicious($request)) {
                    $session->put('mission-control.bad-actor', true);
                }
            }

            // If they have NOT already been validated as a good actor
            // we need to validate them.
            if (! $session->get('mission-control.validated-actor')) {
                $session->put('mission-control.ip', $ipAddress);

                try {
                    [$ip, $geo] = cache()->remember('firewall-ip-scan-'.$ipAddress, now()->addMinutes(60), function () use ($service, $ipAddress) {
                        return $service->lookup($ipAddress);
                    });

                    if (! $session->get('mission-control.valid-ip') && $ip) {
                        // ip is whitelisted, or they are not in the blacklist
                        $session->put('mission-control.valid-ip', true);
                    }

                    if (! $session->get('mission-control.valid-geo') && $geo) {
                        // geo is good (not in a blocked country)
                        $session->put('mission-control.valid-geo', true);
                    }

                    // If any checks fail then set as bad actor.
                    foreach (['geo', 'ip'] as $check) {
                        if (! $session->get("mission-control.valid-{$check}")) {
                            $threat = $service->recordThreat("invalid-{$check}", $request->input());
                            $session->put('mission-control.bad-actor', true);
                        }
                    }

                    // is a malcious action
                    if ($malicious = $service->isMalicious($request)) {
                        $threat = $malicious;
                        $session->put('mission-control.bad-actor', true);
                    }

                    $session->put('mission-control.validated-actor', true);
                } catch (\Throwable $th) {
                    logger('Firewall: '.$th->getMessage());
                }
            }

            if (! is_null($threat) && is_array($threat) && $session->get('mission-control.bad-actor')) {
                event(new AttackDetected(array_merge($threat, ['ip' => $ipAddress])));
            }

            if (! is_null($threat) && ! is_array($threat) && $session->get('mission-control.bad-actor')) {
                event(new AttackDetected(array_merge([
                    'type' => 'Banned Actor',
                    'data' => $request->input()
                ], ['ip' => $ipAddress])));
            }
        }

        return $next($request);
    }

    /**
     * Determine if the firewall should skip the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldSkip($request)
    {
        return $this->isExcludedPath($request) || $this->isHealthCheck($request);
    }

    /**
     * Determine if the request path is excluded from the firewall.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isExcludedPath($request)
    {
        $except = config('mission-control.firewall.except', ['up']);

        return ! empty($except) && $request->is(...$except);
    }

    /**
     * Determine if the request is a load balancer health check. The user agent
     * is only trusted from a private IP so it can't be spoofed from outside.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isHealthCheck($request)
    {
        $userAgents = config('mission-control.firewall.health_check_user_agents', ['ELB-HealthChecker']);
        $userAgent = (string) $request->userAgent();

        if (empty($userAgents) || ! Str::contains($userAgent, $userAgents)) {
            return false;
        }

        $ipAddress = (string) $request->ip();

        return filter_var($ipAddress, FILTER_VALIDATE_IP) !== false
            && filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
