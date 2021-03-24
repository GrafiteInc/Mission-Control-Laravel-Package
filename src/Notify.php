<?php

namespace Grafite\MissionControlLaravel;

use Grafite\MissionControl\NotifyService;

class Notify
{
    public function __construct()
    {
        $this->notifyService = new NotifyService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    public function send($title, $message, $tag)
    {
        return $this->notifyService->send($title, $message, $tag);
    }
}
