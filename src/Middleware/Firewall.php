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
        $session = session();
        $ipAddress = $request->ip();
        $service = app(Security::class);
        $session->put('mission-control.ip', $ipAddress);

        if (! session('mission-control.bad-actor')) {
            [$ip, $geo] = $service->lookup($ipAddress);

            // If their IP has changed.
            if (session('mission-control.ip') !== $ipAddress) {
                $session->forget('mission-control.valid-ip');
            }

            if (! session('mission-control.valid-ip') && $ip) {
                // ip is whitelisted, or they are not in the blacklist
                $session->put('mission-control.valid-ip', true);
            }

            if (! session('mission-control.valid-geo') && $geo) {
                // geo is good (not in a blocked country)
                $session->put('mission-control.valid-geo', true);
            }

            // If any checks fail then set as bad actor.
            foreach (['geo', 'ip'] as $check) {
                if (! session("mission-control.valid-{$check}")) {
                    $threat = $service->recordThreat("invalid-{$check}", $request->input());
                    $session->put('mission-control.bad-actor', true);
                }
            }

            // is a malcious action
            if ($malicious = $service->isMalicious($request)) {
                $threat = $malicious;
                $session->put('mission-control.bad-actor', true);
            }
        }

        if (isset($threat) && session('mission-control.bad-actor')) {
            event(new AttackDetected($threat));
        }

        if (! isset($threat) && session('mission-control.bad-actor')) {
            event(new AttackDetected([
                'type' => 'Banned Actor',
                'data' => $request->input()
            ]));
        }

        return $next($request);
    }
}
