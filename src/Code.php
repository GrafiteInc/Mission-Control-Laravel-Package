<?php

namespace Grafite\MissionControlLaravel;

class Code
{
    public $codeService;

    public function __construct()
    {
        $this->codeService = new CodeService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    public function send(array $payload)
    {
        return $this->codeService->send($payload);
    }
}
