<?php

namespace Grafite\MissionControlLaravel\Middleware;

use Closure;
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
            $session = session();
            $ipAddress = $request->ip();
            $service = app(Security::class);

            if ($session->get('mission-control.validated-actor')) {
                // If their IP has changed.
                if ($session->get('mission-control.ip') !== $ipAddress) {
                    $session->forget('mission-control');
                }
            }

            if (! $session->get('mission-control.validated-actor')) {
                $session->put('mission-control.ip', $ipAddress);

                [$ip, $geo] = $service->lookup($ipAddress);

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
            }

            if (isset($threat) && $session->get('mission-control.bad-actor')) {
                event(new AttackDetected(array_merge($threat, ['ip' => $ipAddress])));
            }

            if (! isset($threat) && $session->get('mission-control.bad-actor')) {
                event(new AttackDetected(array_merge([
                    'type' => 'Banned Actor',
                    'data' => $request->input()
                ], ['ip' => $ipAddress])));
            }
        }

        return $next($request);
    }
}
