<?php

namespace Grafite\MissionControlLaravel;

use Grafite\MissionControl\StatsService;

class Stats
{
    public $statsService;

    public function __construct()
    {
        $this->statsService = new StatsService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    public function send($payload)
    {
        return $this->statsService->send($payload);
    }
}
