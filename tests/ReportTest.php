<?php

use Grafite\MissionControlLaravel\Commands\Report;

class ReportTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $mockCurl = new MockRequest();

        $this->command = app(Report::class);

        $this->command->trafficService->setCurl($mockCurl);
        $this->command->performanceService->setCurl($mockCurl);
        $this->command->performanceService->issueService->setCurl($mockCurl);
    }

    public function testReport()
    {
        $response = $this->command->handle();

        $this->assertTrue($response);
    }
}
