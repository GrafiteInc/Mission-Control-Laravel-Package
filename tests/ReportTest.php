<?php

use Grafite\MissionControlLaravel\Commands\Report;

class ReportTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->command = app(Report::class);

        // $this->command->performanceService->setCurl($mockCurl);
        // $this->command->performanceService->issueService->setCurl($mockCurl);
    }

    public function testReport()
    {
        $response = $this->command->handle();

        $this->assertTrue($response);
    }
}
