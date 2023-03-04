<?php

use Grafite\MissionControlLaravel\Dependencies;

class DependencyTest extends TestCase
{
    public $service;

    public function setUp(): void
    {
        parent::setUp();

        $mockCurl = new MockRequest();

        $this->service = app(Dependencies::class);

        $this->service->dependencyService->setCurl($mockCurl);
    }

    public function testDependency()
    {
        $response = $this->service->send([
            'search' => true,
            'database' => true,
        ]);

        $this->assertTrue($response);
    }
}
