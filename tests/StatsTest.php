<?php

use Grafite\MissionControlLaravel\Stats;

class StatsTest extends TestCase
{
    public $service;

    public function setUp(): void
    {
        parent::setUp();

        $mockCurl = new MockRequest();

        $this->service = app(Stats::class);

        $this->service->statsService->setCurl($mockCurl);
    }

    public function testStats()
    {
        $response = $this->service->send([
            'foo' => 2,
            'bar' => 1,
        ]);

        $this->assertTrue($response);
    }
}
