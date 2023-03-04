<?php

namespace Grafite\MissionControlLaravel;

use Grafite\MissionControl\DependencyService;

class Dependencies
{
    public $dependencyService;

    public function __construct()
    {
        $this->dependencyService = new DependencyService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    public function send($payload)
    {
        return $this->dependencyService->send($payload);
    }
}
