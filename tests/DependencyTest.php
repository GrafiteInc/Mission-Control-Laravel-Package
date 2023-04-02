<?php

use Grafite\MissionControlLaravel\Dependencies;

class DependencyTest extends TestCase
{
    public $service;

    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->service = app(Dependencies::class);
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
