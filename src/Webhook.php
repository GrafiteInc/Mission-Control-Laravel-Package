<?php

namespace Grafite\MissionControlLaravel;

use Grafite\MissionControl\WebhookService;

class Webhook
{
    public function __construct()
    {
        $this->webhookService = new WebhookService(config('mission-control.webhook', null));
    }

    public function send($title, $message, $flag)
    {
        return $this->webhookService->send($title, $message, $flag);
    }
}
