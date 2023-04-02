<?php

use Illuminate\Support\Facades\Http;
use Grafite\MissionControlLaravel\Stats;
use Grafite\MissionControlLaravel\Security;

class SecurityTest extends TestCase
{
    public $service;

    public function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->service = app(Security::class);
    }

    public function testSecurityLookup()
    {
        $response = $this->service->lookup('1.1.1.1');

        $this->assertTrue(is_null($response[0]));
        $this->assertTrue(is_null($response[1]));
    }
}
