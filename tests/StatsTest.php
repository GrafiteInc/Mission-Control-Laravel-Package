<?php

use Grafite\MissionControlLaravel\Stats;

class StatsTest extends TestCase
{
    public $service;

    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->service = app(Stats::class);
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
